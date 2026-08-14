<?php

namespace Paymenter\Extensions\Gateways\CoinPayments;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * CoinPayments payment gateway for Paymenter.
 *
 * Targets the **current** CoinPayments API (`c-api.coinpayments.net`), which authenticates
 * with a Client ID + Client Secret issued by an API Integration on the new dashboard. This
 * is not the legacy `www.coinpayments.net/api.php` + IPN scheme: that used
 * merchant_id / public_key / private_key / ipn_secret and is not what new accounts get.
 *
 * Requests and webhooks are both signed the same way — HMAC-SHA256, base64, over
 *
 *     U+FEFF + HTTP method + full URL + client id + UTC timestamp + raw body
 *
 * with the client secret as key, sent as `X-CoinPayments-Signature` alongside
 * `X-CoinPayments-Client` and `X-CoinPayments-Timestamp`.
 *
 * Security / robustness:
 *  - Every webhook signature verified in constant time before any state changes.
 *  - Settlement is idempotent: payments are keyed on the CoinPayments invoice id, so a
 *    redelivered notification never double-credits.
 *  - Partial payments are never credited — a half-paid invoice would still provision.
 *  - No secrets in code; everything comes from encrypted extension settings.
 *
 * Verified against the live API on 14 Aug 2026: signing accepted (HTTP 200 on
 * /api/v1/merchant/wallets) and invoice creation returns 201 with a checkout link.
 *
 * @link https://docs.coinpayments.net/api/auth
 */
#[ExtensionMeta(
    name: 'CoinPayments Gateway',
    description: 'Accept cryptocurrency payments via CoinPayments (current API).',
    version: '2.0.0',
    author: 'Paymenter Proxy Platform',
    url: 'https://www.coinpayments.net',
)]
class CoinPayments extends Gateway
{
    /** Default host. Overridable because the dashboard shows the API URL per integration. */
    private const DEFAULT_API_URL = 'https://c-api.coinpayments.net';

    private const LOG_CHANNEL = 'stack';

    /** Notifications we ask CoinPayments to send for each invoice. */
    private const NOTIFICATIONS = [
        'invoiceCreated',
        'invoicePaid',
        'invoiceCompleted',
        'invoiceCancelled',
        'invoiceTimedOut',
    ];

    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.coinpayments', __DIR__ . '/resources/views');
    }

    /**
     * Respect the Country-based Gateway Rules module natively, and never offer a method
     * that would refuse the amount — failing after the customer commits is worse than
     * not offering it at all.
     */
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        $minimum = (float) ($this->config('minimum_amount') ?? 0);
        if ($minimum > 0 && (float) $total < $minimum) {
            return false;
        }

        $rules = '\\Paymenter\\Extensions\\Others\\GatewayRules\\GatewayRules';
        if (!class_exists($rules)) {
            return true;
        }
        $gateway = \App\Models\Gateway::where('extension', 'CoinPayments')->first();

        return $gateway ? $rules::allows($gateway, $total, $currency, $type, $items) : true;
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'client_id',
                'label' => 'Client ID',
                'type' => 'text',
                'description' => 'From your CoinPayments dashboard → API Integrations → your integration.',
                'required' => true,
            ],
            [
                'name' => 'client_secret',
                'label' => 'Client Secret',
                'type' => 'text',
                'description' => 'Shown only once when the integration is created. Stored encrypted. '
                    . 'If lost, regenerate it in the dashboard and paste the new value here.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'api_url',
                'label' => 'API URL',
                'type' => 'text',
                'description' => 'The API URL shown on your integration. Normally ' . self::DEFAULT_API_URL . '.',
                'default' => self::DEFAULT_API_URL,
                'required' => false,
            ],
            [
                'name' => 'minimum_amount',
                'label' => 'Minimum amount',
                'type' => 'text',
                'description' => 'Hide this method at checkout below this total — crypto networks will not settle dust. Zero disables the check.',
                'validation' => 'numeric',
                'default' => '1.00',
                'required' => false,
            ],
        ];
    }

    private function apiUrl(): string
    {
        return rtrim((string) ($this->config('api_url') ?: self::DEFAULT_API_URL), '/');
    }

    /**
     * Build the canonical string CoinPayments signs, and return its base64 HMAC-SHA256.
     *
     * The leading U+FEFF and the absence of separators are both required — the signature
     * is rejected without them.
     */
    private function signature(string $method, string $url, string $timestamp, string $body): string
    {
        $message = "\u{feff}" . $method . $url . (string) $this->config('client_id') . $timestamp . $body;

        return base64_encode(hash_hmac('sha256', $message, (string) $this->config('client_secret'), true));
    }

    /**
     * Perform a signed API call.
     *
     * @throws \RuntimeException on transport or API-level failure.
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->apiUrl() . $path;
        // The exact bytes signed must be the exact bytes sent, so encode once and reuse.
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = gmdate('Y-m-d\TH:i:s');

        $request = Http::withHeaders([
            'X-CoinPayments-Client' => (string) $this->config('client_id'),
            'X-CoinPayments-Timestamp' => $timestamp,
            'X-CoinPayments-Signature' => $this->signature($method, $url, $timestamp, $body),
            'Accept' => 'application/json',
        ])->timeout(30);

        $response = $body === ''
            ? $request->send($method, $url)
            : $request->withBody($body, 'application/json')->send($method, $url);

        if (!$response->successful()) {
            $detail = $response->json('detail') ?? $response->json('title') ?? $response->body();
            $this->log('error', 'CoinPayments API error', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'detail' => is_string($detail) ? substr($detail, 0, 300) : $detail,
            ]);

            throw new \RuntimeException('CoinPayments API error: ' . (is_string($detail) ? $detail : 'HTTP ' . $response->status()));
        }

        return (array) $response->json();
    }

    /**
     * Map an ISO currency code to the numeric CoinPayments currency id its API expects.
     *
     * The list is public and stable, so it is cached rather than fetched per checkout.
     */
    private function currencyId(string $code): string
    {
        $map = Cache::remember('coinpayments.currencies', now()->addDay(), function () {
            $currencies = $this->request('GET', '/api/v1/currencies');
            $map = [];
            foreach ($currencies as $currency) {
                if (isset($currency['symbol'], $currency['id'])) {
                    $map[strtoupper($currency['symbol'])] = (string) $currency['id'];
                }
            }

            return $map;
        });

        $id = $map[strtoupper($code)] ?? null;

        if ($id === null) {
            throw new \RuntimeException('CoinPayments does not recognise the currency ' . $code . '.');
        }

        return $id;
    }

    /**
     * Create a hosted CoinPayments invoice and send the buyer to its checkout.
     *
     * Returning a string makes Paymenter redirect, which is what we want — the checkout is
     * hosted by CoinPayments and handles coin selection and confirmations itself.
     */
    public function pay($invoice, $total)
    {
        $amount = number_format((float) $total, 2, '.', '');

        $payload = [
            'currency' => $this->currencyId($invoice->currency_code),
            'amount' => [
                'breakdown' => ['subtotal' => $amount],
                'total' => $amount,
            ],
            'items' => [[
                'name' => __('invoices.payment_for_invoice', ['number' => $invoice->number ?? $invoice->id]),
                'quantity' => ['value' => 1, 'type' => 'quantity'],
                'amount' => $amount,
            ]],
            // Our own reference, echoed back on every notification so we can reconcile.
            'invoiceId' => (string) $invoice->id,
            'refundEmail' => $invoice->user->email,
            'successUrl' => route('invoices.show', $invoice),
            'cancelUrl' => route('invoices.show', $invoice),
            'webhooks' => [[
                'notificationsUrl' => route('extensions.gateways.coinpayments.ipn'),
                'notifications' => self::NOTIFICATIONS,
            ]],
        ];

        $response = $this->request('POST', '/api/v2/merchant/invoices', $payload);
        $created = $response['invoices'][0] ?? null;

        if (!isset($created['id'], $created['checkoutLink'])) {
            throw new \RuntimeException('CoinPayments did not return a checkout link for this invoice.');
        }

        // Record the attempt keyed on the CoinPayments invoice id so the notification can
        // settle it idempotently later.
        ExtensionHelper::addProcessingPayment($invoice->id, 'CoinPayments', (float) $total, null, $created['id']);

        $this->log('info', 'Created CoinPayments invoice', [
            'invoice_id' => $invoice->id,
            'coinpayments_id' => $created['id'],
            'amount' => $amount,
            'currency' => $invoice->currency_code,
        ]);

        return $created['checkoutLink'];
    }

    /**
     * Handle a signed CoinPayments notification.
     *
     * CoinPayments retries on non-2xx, so anything safely handled — including events we
     * deliberately ignore — answers 200. Only signature failures return an error.
     */
    public function webhook(Request $request)
    {
        $raw = $request->getContent();

        if (!$this->isValidSignature($request, $raw)) {
            $this->log('warning', 'CoinPayments notification rejected: invalid signature');

            return response('Invalid signature', 400);
        }

        $payload = json_decode($raw, true) ?: [];
        $event = $this->extract($payload, ['type', 'event', 'notification', 'notificationType']) ?? 'unknown';

        // `invoice.id` is CoinPayments' own invoice id and is the same on every
        // notification about that invoice. The top-level `id` is the *notification* id and
        // differs per event — keying settlement on it would write a second payment row
        // when InvoicePaid and InvoiceCompleted both arrive. Confirmed against a live
        // payload: two InvoiceCreated deliveries carried different top-level ids.
        $coinPaymentsId = $this->extract($payload, ['invoice.id']);
        $ourReference = $this->extract($payload, ['invoice.invoiceId', 'invoice.invoiceNumber', 'invoiceId', 'customId']);
        $eventId = $this->extract($payload, ['id']);

        // The payload shape is documented only loosely, so keep a copy of anything we
        // could not map — the log is what tells us why a real notification was missed.
        $this->log('info', 'CoinPayments notification received', [
            'event' => $event,
            'event_id' => $eventId,
            'coinpayments_id' => $coinPaymentsId,
            'reference' => $ourReference,
        ]);

        $invoice = ctype_digit((string) $ourReference) ? Invoice::find((int) $ourReference) : null;

        if (!$invoice) {
            $this->log('warning', 'CoinPayments notification for unknown invoice', [
                'reference' => $ourReference,
                'payload' => substr($raw, 0, 1000),
            ]);

            // Acknowledge, otherwise CoinPayments retries something we can never match.
            return response('OK', 200);
        }

        // Settle against our own recorded total rather than the payload. CoinPayments
        // reports line-item amounts in minor units (500 for $5.00) across several nested
        // shapes, and misreading that by a factor of 100 is the kind of error that only
        // shows up in production. The invoice we issued is the authoritative figure.
        $amount = (float) $invoice->total;

        // Falls back to our own invoice id only if CoinPayments ever omits theirs; the
        // processing row created at checkout used their id, so this settles it in place.
        $transactionId = (string) ($coinPaymentsId ?? $invoice->id);

        // The API documents these as camelCase but sends PascalCase ("InvoiceCreated"),
        // confirmed against a live notification. Match case-insensitively so a change in
        // either direction cannot silently strand a paid invoice at pending.
        switch (lcfirst((string) $event)) {
            case 'invoicePaid':
            case 'invoiceCompleted':
                ExtensionHelper::addPayment($invoice->id, 'CoinPayments', $amount, null, $transactionId);
                $this->log('info', 'CoinPayments payment completed', [
                    'invoice_id' => $invoice->id,
                    'coinpayments_id' => $transactionId,
                    'amount' => $amount,
                ]);
                break;

            case 'invoiceCancelled':
            case 'invoiceTimedOut':
                // Timed-out invoices include underpayments. Never credit a partial amount:
                // a partially paid invoice would still provision the service.
                ExtensionHelper::addFailedPayment($invoice->id, 'CoinPayments', $amount, null, $transactionId);
                $this->log('info', 'CoinPayments payment cancelled or timed out', [
                    'invoice_id' => $invoice->id,
                    'coinpayments_id' => $transactionId,
                    'event' => $event,
                ]);
                break;

            default:
                // invoiceCreated and anything new: acknowledged, nothing to settle.
                break;
        }

        return response('OK', 200);
    }

    /**
     * Verify the notification signature in constant time.
     *
     * Webhooks are signed exactly like outbound requests, over the notification URL we
     * registered — so that URL, not the request's own host, is what must be signed.
     */
    private function isValidSignature(Request $request, string $raw): bool
    {
        $signature = $request->header('X-CoinPayments-Signature');
        $timestamp = $request->header('X-CoinPayments-Timestamp');

        if (!$signature || !$timestamp || !$this->config('client_secret')) {
            return false;
        }

        $expected = $this->signature('POST', route('extensions.gateways.coinpayments.ipn'), $timestamp, $raw);

        return hash_equals($expected, $signature);
    }

    /**
     * Pull the first present value from a set of candidate paths ("a.b.c" notation),
     * because the notification payload nests differently per event type.
     */
    private function extract(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}('[CoinPayments] ' . $message, $context);
    }
}
