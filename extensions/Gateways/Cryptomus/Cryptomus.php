<?php

namespace Paymenter\Extensions\Gateways\Cryptomus;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Cryptomus payment gateway for Paymenter (official API).
 *
 * Creates a hosted Cryptomus payment and settles via webhook. Requests and webhook
 * callbacks are authenticated with Cryptomus's `sign` scheme:
 *   sign = md5( base64(json_body) . payment_api_key )
 *
 * Security / robustness (spec item 4):
 *  - `sign` verified on every webhook (constant-time compare); invalid → rejected.
 *  - Idempotent settlement keyed on the Cryptomus payment uuid, so duplicate
 *    callbacks never double-credit.
 *  - Encrypted credentials, structured logging, error handling; no secrets logged.
 *
 * @link https://doc.cryptomus.com/
 */
#[ExtensionMeta(
    name: 'Cryptomus Gateway',
    description: 'Accept cryptocurrency payments via Cryptomus.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
    url: 'https://cryptomus.com',
)]
class Cryptomus extends Gateway
{
    private const API_URL = 'https://api.cryptomus.com/v1';

    private const LOG_CHANNEL = 'stack';

    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.cryptomus', __DIR__ . '/resources/views');
    }

    /** Respect Country-based Gateway Rules (item 5) natively if installed. */
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        // Do not offer a method that will refuse the amount. Showing it and then
        // failing after the customer commits is worse than not offering it.
        $minimum = (float) ($this->config('minimum_amount') ?? 0);
        if ($minimum > 0 && (float) $total < $minimum) {
            return false;
        }

        $rules = '\\Paymenter\\Extensions\\Others\\GatewayRules\\GatewayRules';
        if (!class_exists($rules)) {
            return true;
        }
        $gateway = \App\Models\Gateway::where('extension', 'Cryptomus')->first();

        return $gateway ? $rules::allows($gateway, $total, $currency, $type, $items) : true;
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'merchant_id',
                'label' => 'Merchant UUID',
                'type' => 'text',
                'description' => 'Cryptomus Merchant UUID (Dashboard → Settings).',
                'required' => true,
            ],
            [
                'name' => 'payment_api_key',
                'label' => 'Payment API Key',
                'type' => 'text',
                'description' => 'Cryptomus Payment API key. Stored encrypted; used to sign requests and verify webhooks.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'minimum_amount',
                'label' => 'Minimum amount',
                'type' => 'text',
                'description' => 'Hide this method at checkout when the invoice total is below this. Cryptomus rejects amounts below its per-coin floor. Zero disables the check.',
                'validation' => 'numeric',
                'default' => '1.00',
                'required' => false,
            ],
        ];
    }

    /**
     * Create a Cryptomus payment and redirect the buyer to the hosted page.
     */
    public function pay($invoice, $total)
    {
        $body = [
            'amount' => (string) number_format((float) $total, 2, '.', ''),
            'currency' => $invoice->currency_code,
            'order_id' => (string) $invoice->id,
            'url_callback' => route('extensions.gateways.cryptomus.webhook'),
            'url_return' => route('invoices.show', $invoice),
            'url_success' => route('invoices.show', $invoice),
        ];

        $result = $this->request('/payment', $body);
        $data = $result['result'] ?? [];

        // Record a processing transaction keyed on the Cryptomus payment uuid.
        if (!empty($data['uuid'])) {
            ExtensionHelper::addProcessingPayment($invoice->id, 'Cryptomus', (float) $total, null, $data['uuid']);
        }

        $this->log('info', 'Created Cryptomus payment', [
            'invoice_id' => $invoice->id,
            'uuid' => $data['uuid'] ?? null,
        ]);

        return view('gateways.cryptomus::pay', [
            'invoice' => $invoice,
            'total' => $total,
            'paymentUrl' => $data['url'] ?? null,
        ]);
    }

    /**
     * Signed Cryptomus API call. Returns the decoded JSON on success.
     *
     * @throws \RuntimeException on transport or API-level error
     */
    private function request(string $path, array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $sign = md5(base64_encode($payload) . (string) $this->config('payment_api_key'));

        $response = Http::withHeaders([
            'merchant' => (string) $this->config('merchant_id'),
            'sign' => $sign,
            'Content-Type' => 'application/json',
        ])->withBody($payload, 'application/json')
            ->timeout(20)
            ->post(self::API_URL . $path);

        $json = $response->json() ?? [];

        if (!$response->successful() || (($json['state'] ?? 1) !== 0)) {
            $msg = $json['message'] ?? (is_array($json['errors'] ?? null) ? json_encode($json['errors']) : 'HTTP ' . $response->status());
            $this->log('error', 'Cryptomus API error', ['path' => $path, 'error' => $msg]);
            throw new \RuntimeException('Cryptomus API error: ' . $msg);
        }

        return $json;
    }

    /**
     * Handle a Cryptomus webhook. Verify the `sign` field, then settle idempotently.
     */
    public function webhook(Request $request)
    {
        $data = $request->all();
        $sign = $data['sign'] ?? null;

        if (!$this->isValidSignature($data, $sign)) {
            $this->log('warning', 'Cryptomus webhook rejected: invalid sign');

            return response()->json(['error' => 'Invalid sign'], 400);
        }

        $uuid = $data['uuid'] ?? null;
        $orderId = $data['order_id'] ?? null;
        $status = $data['status'] ?? null;
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;

        $invoice = $orderId ? Invoice::find($orderId) : null;
        if (!$invoice || !$uuid) {
            return response()->json(['result' => 'ok']); // acknowledge; nothing to reconcile
        }

        // Cryptomus payment statuses:
        //   paid / paid_over          → success
        //   cancel / fail / system_fail / wrong_amount → failed
        //   check / process / confirm_check → still processing
        if (in_array($status, ['paid', 'paid_over'], true)) {
            ExtensionHelper::addPayment($invoice->id, 'Cryptomus', $amount, null, $uuid);
            $this->log('info', 'Cryptomus payment completed', ['invoice_id' => $invoice->id, 'uuid' => $uuid]);
        } elseif (in_array($status, ['cancel', 'fail', 'system_fail', 'wrong_amount'], true)) {
            ExtensionHelper::addFailedPayment($invoice->id, 'Cryptomus', $amount, null, $uuid);
            $this->log('info', 'Cryptomus payment failed', ['invoice_id' => $invoice->id, 'status' => $status]);
        } else {
            ExtensionHelper::addProcessingPayment($invoice->id, 'Cryptomus', $amount, null, $uuid);
        }

        return response()->json(['result' => 'ok']);
    }

    /**
     * Verify the webhook `sign`: md5(base64(json body without sign) . api key).
     */
    private function isValidSignature(array $data, ?string $sign): bool
    {
        if (!$sign) {
            return false;
        }
        unset($data['sign']);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $expected = md5(base64_encode($payload) . (string) $this->config('payment_api_key'));

        return hash_equals($expected, $sign);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}('[Cryptomus] ' . $message, $context);
    }
}
