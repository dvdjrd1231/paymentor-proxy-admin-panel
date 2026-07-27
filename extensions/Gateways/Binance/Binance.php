<?php

namespace Paymenter\Extensions\Gateways\Binance;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\InvoiceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Binance Pay gateway for Paymenter — official Merchant API only.
 *
 * Flow:
 *  - pay(): creates a Binance Pay order (v3) and redirects the buyer to the hosted
 *    checkout. A processing transaction is recorded keyed on our merchantTradeNo.
 *  - webhook(): receives Binance Pay notifications, verifies the RSA signature
 *    against Binance's public certificate, and settles idempotently.
 *
 * Security / robustness (spec item 4):
 *  - Requests are signed HMAC-SHA512 per Binance's scheme; nothing is hard-coded.
 *  - Webhook signature verified with Binance's certificate (constant-time compare
 *    is inherent to RSA verify); merchant id implicitly bound via credentials.
 *  - Idempotent settlement keyed on merchantTradeNo (updateOrCreate on the
 *    transaction), so duplicate notifications never double-credit.
 *  - Structured logging on every step; errors handled, never leak secrets.
 *
 * @link https://developers.binance.com/docs/binance-pay/api-order-create-v3
 */
#[ExtensionMeta(
    name: 'Binance Pay Gateway',
    description: 'Accept crypto payments via Binance Pay (official Merchant API).',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
    url: 'https://pay.binance.com',
)]
class Binance extends Gateway
{
    private const BASE_URL = 'https://bpay.binanceapi.com';

    private const LOG_CHANNEL = 'stack';

    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.binance', __DIR__ . '/resources/views');
    }

    /** Respect Country-based Gateway Rules (item 5) natively if installed. */
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        $rules = '\\Paymenter\\Extensions\\Others\\GatewayRules\\GatewayRules';
        if (!class_exists($rules)) {
            return true;
        }
        $gateway = \App\Models\Gateway::where('extension', 'Binance')->first();

        return $gateway ? $rules::allows($gateway, $total, $currency, $type, $items) : true;
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'Binance Pay API Key (Certificate SN)',
                'type' => 'text',
                'description' => 'Merchant API Key from Binance Merchant dashboard. Stored encrypted.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'api_secret',
                'label' => 'Binance Pay API Secret',
                'type' => 'text',
                'description' => 'Merchant API Secret. Stored encrypted; used to sign requests.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'pay_currency',
                'label' => 'Order Currency',
                'type' => 'text',
                'description' => 'Fiat/crypto currency for the order amount (e.g. USDT, BUSD, EUR). Must be supported by your Binance Pay merchant account.',
                'required' => true,
            ],
        ];
    }

    /**
     * Create a Binance Pay order and redirect the buyer to checkout.
     */
    public function pay($invoice, $total)
    {
        // Unique, ≤32 char alphanumeric merchant trade number. We reconcile the
        // webhook back to the invoice via the processing transaction below.
        $merchantTradeNo = 'PM' . $invoice->id . strtoupper(Str::random(18));
        $merchantTradeNo = substr($merchantTradeNo, 0, 32);

        $body = [
            'env' => ['terminalType' => 'WEB'],
            'merchantTradeNo' => $merchantTradeNo,
            'orderAmount' => (float) number_format((float) $total, 2, '.', ''),
            'currency' => $this->config('pay_currency'),
            'goods' => [
                'goodsType' => '02', // virtual goods
                'goodsCategory' => 'Z000', // others
                'referenceGoodsId' => (string) $invoice->id,
                'goodsName' => __('invoices.payment_for_invoice', ['number' => $invoice->number ?? $invoice->id]),
            ],
            'returnUrl' => route('invoices.show', $invoice),
            'cancelUrl' => route('invoices.show', $invoice),
        ];

        $result = $this->request('/binancepay/openapi/v3/order', $body);
        $data = $result['data'] ?? [];

        // Record a processing transaction keyed on our merchantTradeNo so the
        // webhook can settle it idempotently and be reconciled to this invoice.
        ExtensionHelper::addProcessingPayment($invoice->id, 'Binance', (float) $total, null, $merchantTradeNo);

        $this->log('info', 'Created Binance Pay order', [
            'invoice_id' => $invoice->id,
            'merchantTradeNo' => $merchantTradeNo,
            'prepayId' => $data['prepayId'] ?? null,
        ]);

        return view('gateways.binance::pay', [
            'invoice' => $invoice,
            'total' => $total,
            'checkoutUrl' => $data['checkoutUrl'] ?? ($data['universalUrl'] ?? null),
            'qrcodeLink' => $data['qrcodeLink'] ?? null,
            'currency' => $this->config('pay_currency'),
        ]);
    }

    /**
     * Signed Binance Pay API call. Returns the decoded JSON on success.
     *
     * @throws \RuntimeException on transport or API-level error
     */
    private function request(string $path, array $body): array
    {
        $timestamp = (string) (int) (microtime(true) * 1000);
        $nonce = Str::random(32);
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES);

        $toSign = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";
        $signature = strtoupper(hash_hmac('sha512', $toSign, (string) $this->config('api_secret')));

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'BinancePay-Timestamp' => $timestamp,
            'BinancePay-Nonce' => $nonce,
            'BinancePay-Certificate-SN' => (string) $this->config('api_key'),
            'BinancePay-Signature' => $signature,
        ])->withBody($payload, 'application/json')
            ->timeout(20)
            ->post(self::BASE_URL . $path);

        $json = $response->json() ?? [];

        if (!$response->successful() || (($json['status'] ?? null) !== 'SUCCESS')) {
            $msg = $json['errorMessage'] ?? ($json['code'] ?? ('HTTP ' . $response->status()));
            $this->log('error', 'Binance Pay API error', ['path' => $path, 'error' => $msg]);
            throw new \RuntimeException('Binance Pay API error: ' . $msg);
        }

        return $json;
    }

    /**
     * Handle a Binance Pay webhook notification.
     *
     * Binance expects a 200 with {"returnCode":"SUCCESS","returnMessage":null} once
     * handled, otherwise it retries. We only reject on signature failure.
     */
    public function webhook(Request $request)
    {
        $raw = $request->getContent();
        $timestamp = $request->header('BinancePay-Timestamp');
        $nonce = $request->header('BinancePay-Nonce');
        $signature = $request->header('BinancePay-Signature');

        if (!$this->isValidSignature($raw, $timestamp, $nonce, $signature)) {
            $this->log('warning', 'Binance Pay webhook rejected: invalid signature');

            return response()->json(['returnCode' => 'FAIL', 'returnMessage' => 'Invalid signature'], 400);
        }

        $event = json_decode($raw, true) ?? [];
        $bizStatus = $event['bizStatus'] ?? null;
        $data = json_decode($event['data'] ?? '{}', true) ?? [];
        $merchantTradeNo = $data['merchantTradeNo'] ?? null;

        if (!$merchantTradeNo) {
            return response()->json(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
        }

        // Reconcile to the invoice via the processing transaction we created in pay().
        $transaction = InvoiceTransaction::where('transaction_id', $merchantTradeNo)->first();
        if (!$transaction) {
            $this->log('warning', 'Binance Pay webhook for unknown merchantTradeNo', ['merchantTradeNo' => $merchantTradeNo]);

            return response()->json(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
        }

        $invoiceId = $transaction->invoice_id;
        $amount = isset($data['orderAmount']) ? (float) $data['orderAmount'] : (float) $transaction->amount;

        if ($bizStatus === 'PAY_SUCCESS') {
            ExtensionHelper::addPayment($invoiceId, 'Binance', $amount, null, $merchantTradeNo);
            $this->log('info', 'Binance Pay payment completed', ['invoice_id' => $invoiceId, 'merchantTradeNo' => $merchantTradeNo]);
        } elseif (in_array($bizStatus, ['PAY_CLOSED', 'PAY_EXPIRED', 'PAY_ERROR'], true)) {
            ExtensionHelper::addFailedPayment($invoiceId, 'Binance', $amount, null, $merchantTradeNo);
            $this->log('info', 'Binance Pay payment failed/closed', ['invoice_id' => $invoiceId, 'bizStatus' => $bizStatus]);
        }

        return response()->json(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
    }

    /**
     * Verify the webhook RSA signature against Binance's public certificate.
     */
    private function isValidSignature(string $payload, ?string $timestamp, ?string $nonce, ?string $signature): bool
    {
        if (!$timestamp || !$nonce || !$signature) {
            return false;
        }

        $publicKey = $this->certificatePublicKey();
        if (!$publicKey) {
            $this->log('error', 'Binance Pay: no public certificate available to verify webhook');

            return false;
        }

        $toVerify = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";

        // Binance may return the key already PEM-wrapped or as raw base64 DER.
        $pem = str_contains($publicKey, 'BEGIN')
            ? $publicKey
            : "-----BEGIN PUBLIC KEY-----\n" . chunk_split($publicKey, 64, "\n") . "-----END PUBLIC KEY-----\n";

        $ok = openssl_verify($toVerify, base64_decode($signature), $pem, OPENSSL_ALGO_SHA256);

        return $ok === 1;
    }

    /**
     * Fetch (and cache) Binance's webhook-verification public certificate.
     */
    private function certificatePublicKey(): ?string
    {
        return Cache::remember('binancepay_cert_' . md5((string) $this->config('api_key')), now()->addHours(12), function () {
            try {
                $result = $this->request('/binancepay/openapi/certificates', ['certificateType' => 'RSA']);

                return $result['data'][0]['certPublicKey'] ?? null;
            } catch (\Throwable $e) {
                $this->log('error', 'Binance Pay: failed to fetch certificate', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}('[Binance] ' . $message, $context);
    }
}
