<?php

namespace Paymenter\Extensions\Gateways\CoinPayments;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * CoinPayments payment gateway for Paymenter.
 *
 * Uses the CoinPayments API (create_transaction) to build a hosted crypto
 * checkout, and validates Instant Payment Notifications (IPN) via the merchant
 * IPN secret (HMAC-SHA512 over the raw request body).
 *
 * Security / robustness:
 *  - Signature validation on every IPN (constant-time compare).
 *  - Merchant-id check to reject IPNs meant for other accounts.
 *  - Idempotent settlement: payments are recorded keyed on the CoinPayments
 *    `txn_id`, so duplicate IPNs never double-credit an invoice.
 *  - No secrets in code — everything comes from encrypted extension settings.
 *  - Structured logging on every state transition and error.
 *
 * @link https://www.coinpayments.net/apidoc
 */
#[ExtensionMeta(
    name: 'CoinPayments Gateway',
    description: 'Accept cryptocurrency payments via CoinPayments (API + IPN).',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
    url: 'https://www.coinpayments.net',
)]
class CoinPayments extends Gateway
{
    private const API_URL = 'https://www.coinpayments.net/api.php';

    private const LOG_CHANNEL = 'stack';

    /**
     * Register the IPN webhook route when the extension boots.
     */
    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.coinpayments', __DIR__ . '/resources/views');
    }

    /**
     * Admin configuration fields (stored encrypted where marked).
     */
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'description' => 'Your CoinPayments Merchant ID (Account Settings → Merchant Settings).',
                'required' => true,
            ],
            [
                'name' => 'public_key',
                'label' => 'API Public Key',
                'type' => 'text',
                'description' => 'CoinPayments API public key (My Account → API Keys). Needs create_transaction permission.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'private_key',
                'label' => 'API Private Key',
                'type' => 'text',
                'description' => 'CoinPayments API private key. Stored encrypted; used to sign API requests.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'ipn_secret',
                'label' => 'IPN Secret',
                'type' => 'text',
                'description' => 'The IPN Secret configured in Account Settings → Merchant Settings. Used to validate incoming IPNs.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'receive_currency',
                'label' => 'Receive Currency',
                'type' => 'text',
                'description' => 'Coin you want to receive (currency2), e.g. BTC, LTCT (testnet), USDT.TRC20. CoinPayments auto-converts the buyer\'s coin.',
                'required' => true,
            ],
        ];
    }

    /**
     * Build a hosted CoinPayments checkout for the given invoice.
     *
     * Returns a Blade view that shows/redirects the buyer to the CoinPayments
     * checkout URL. The CoinPayments `txn_id` is stored on the invoice
     * transaction so IPNs can be reconciled idempotently.
     */
    public function pay($invoice, $total)
    {
        $fields = [
            'version'     => 1,
            'cmd'         => 'create_transaction',
            'key'         => $this->config('public_key'),
            'format'      => 'json',
            'amount'      => number_format((float) $total, 8, '.', ''),
            'currency1'   => $invoice->currency_code,
            'currency2'   => $this->config('receive_currency'),
            'buyer_email' => $invoice->user->email,
            'item_name'   => __('invoices.payment_for_invoice', ['number' => $invoice->number ?? $invoice->id]),
            'invoice'     => (string) $invoice->id,
            'custom'      => (string) $invoice->id,
            'ipn_url'     => route('extensions.gateways.coinpayments.ipn'),
            'success_url' => route('invoices.show', $invoice),
            'cancel_url'  => route('invoices.show', $invoice),
        ];

        $result = $this->apiCall($fields);

        // Record a pending transaction keyed on the CoinPayments txn_id so the
        // later IPN can settle it idempotently.
        ExtensionHelper::addProcessingPayment(
            $invoice->id,
            'CoinPayments',
            (float) $total,
            null,
            $result->txn_id,
        );

        $this->log('info', 'Created CoinPayments transaction', [
            'invoice_id' => $invoice->id,
            'txn_id'     => $result->txn_id,
            'amount'     => $total,
            'currency'   => $invoice->currency_code,
        ]);

        return view('gateways.coinpayments::pay', [
            'invoice'      => $invoice,
            'total'        => $total,
            'checkoutUrl'  => $result->checkout_url,
            'statusUrl'    => $result->status_url ?? null,
            'qrcodeUrl'    => $result->qrcode_url ?? null,
            'address'      => $result->address ?? null,
            'cryptoAmount' => $result->amount ?? null,
            'currency2'    => $this->config('receive_currency'),
        ]);
    }

    /**
     * Perform a signed CoinPayments API call and return the `result` object.
     *
     * The request body is signed with the private key (HMAC-SHA512). The exact
     * bytes that are signed are the exact bytes transmitted, to avoid encoding
     * mismatches.
     *
     * @throws \RuntimeException on transport or API-level error.
     */
    private function apiCall(array $fields): object
    {
        $body = http_build_query($fields);
        $hmac = hash_hmac('sha512', $body, (string) $this->config('private_key'));

        $response = Http::withHeaders([
            'HMAC'         => $hmac,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->withBody($body, 'application/x-www-form-urlencoded')
            ->post(self::API_URL);

        if (!$response->successful()) {
            $this->log('error', 'CoinPayments API transport error', [
                'status' => $response->status(),
                'cmd'    => $fields['cmd'] ?? null,
            ]);
            throw new \RuntimeException('CoinPayments API request failed (HTTP ' . $response->status() . ').');
        }

        $json = $response->object();

        if (!isset($json->error) || $json->error !== 'ok') {
            $message = $json->error ?? 'Unknown CoinPayments API error';
            $this->log('error', 'CoinPayments API error', [
                'cmd'   => $fields['cmd'] ?? null,
                'error' => $message,
            ]);
            throw new \RuntimeException('CoinPayments API error: ' . $message);
        }

        return $json->result;
    }

    /**
     * Handle an incoming CoinPayments IPN.
     *
     * CoinPayments always returns HTTP 200 on success; a non-200 makes it retry,
     * so we return 200 for anything we have safely handled (including ignored
     * events) and an error status only for signature/validation failures.
     */
    public function webhook(Request $request)
    {
        $raw = $request->getContent();
        $signature = $request->header('HMAC');
        $merchant = $request->input('merchant');
        $txnId = $request->input('txn_id');

        // 1. Signature present + valid (HMAC-SHA512 of the raw body, IPN secret).
        if (!$this->isValidSignature($raw, $signature)) {
            $this->log('warning', 'CoinPayments IPN rejected: invalid signature', ['txn_id' => $txnId]);

            return response('Invalid signature', 400);
        }

        // 2. Merchant id must match ours (defence-in-depth).
        if (!hash_equals((string) $this->config('merchant_id'), (string) $merchant)) {
            $this->log('warning', 'CoinPayments IPN rejected: merchant mismatch', ['txn_id' => $txnId]);

            return response('Invalid merchant', 400);
        }

        $status = (int) $request->input('status');
        $invoiceId = $request->input('custom') ?: $request->input('invoice');
        $amount = (float) $request->input('amount1'); // fiat amount in currency1
        $fee = $request->input('fee') !== null ? (float) $request->input('fee') : null;

        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            // Nothing to reconcile against; acknowledge so CoinPayments stops retrying.
            $this->log('warning', 'CoinPayments IPN for unknown invoice', ['invoice_id' => $invoiceId, 'txn_id' => $txnId]);

            return response('OK', 200);
        }

        // CoinPayments status semantics:
        //   >= 100 or == 2 : payment complete
        //   0..99          : pending / in progress
        //   < 0            : cancelled / timed out / error
        if ($status >= 100 || $status === 2) {
            // Idempotent: updateOrCreate keyed on txn_id inside a locked tx.
            ExtensionHelper::addPayment($invoice->id, 'CoinPayments', $amount, $fee, $txnId);
            $this->log('info', 'CoinPayments payment completed', ['invoice_id' => $invoice->id, 'txn_id' => $txnId, 'amount' => $amount]);
        } elseif ($status < 0) {
            ExtensionHelper::addFailedPayment($invoice->id, 'CoinPayments', $amount, $fee, $txnId);
            $this->log('info', 'CoinPayments payment failed/cancelled', ['invoice_id' => $invoice->id, 'txn_id' => $txnId, 'status_text' => $request->input('status_text')]);
        } else {
            ExtensionHelper::addProcessingPayment($invoice->id, 'CoinPayments', $amount, $fee, $txnId);
            $this->log('debug', 'CoinPayments payment pending', ['invoice_id' => $invoice->id, 'txn_id' => $txnId, 'status' => $status]);
        }

        return response('IPN OK', 200);
    }

    /**
     * Validate the IPN HMAC signature in constant time.
     */
    private function isValidSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) $this->config('ipn_secret');

        if ($signature === null || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}('[CoinPayments] ' . $message, $context);
    }
}
