<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credit returned to a client against an invoice.
 *
 * See the `ext_ao_refunds` migration for why this is a record of its own rather than a
 * negative invoice transaction. The money itself moves on `credits`; this says why.
 */
class Refund extends Model
{
    protected $table = 'ext_ao_refunds';

    protected $fillable = [
        'invoice_id', 'service_id', 'user_id', 'amount', 'currency_code', 'reason', 'admin_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** What has already been refunded against one invoice. */
    public static function totalFor(int $invoiceId): float
    {
        return (float) static::where('invoice_id', $invoiceId)->sum('amount');
    }

    /**
     * Has this service already been credited for its unused time?
     *
     * The cancellation credit is automatic, so this is the thing standing between a
     * duplicated event and paying a customer twice.
     */
    public static function issuedForService(int $serviceId): bool
    {
        return static::where('service_id', $serviceId)->exists();
    }
}
