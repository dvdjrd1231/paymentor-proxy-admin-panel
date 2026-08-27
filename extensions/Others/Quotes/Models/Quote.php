<?php

namespace Paymenter\Extensions\Others\Quotes\Models;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A priced proposal, and where it got to.
 *
 * @property string $status draft | sent | accepted | declined | expired
 */
class Quote extends Model
{
    protected $table = 'ext_quotes';

    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort')->orderBy('id');
    }

    /** What the customer is being asked to agree to. */
    public function total(): float
    {
        return round($this->items->sum(fn (QuoteItem $item): float => $item->total()), 2);
    }

    /**
     * Whether the customer can still act on it.
     *
     * A quote past its date is answerable right up until the sweep marks it expired, and
     * deliberately so: a customer accepting at one minute past midnight on the closing day
     * has done what was asked of them, and losing that sale to a cron schedule would be a
     * self-inflicted wound. The sweep closes it; the clock alone does not.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /** Past its date but not yet swept. Shown to the customer as a warning, not a refusal. */
    public function isLapsed(): bool
    {
        return $this->isOpen()
            && $this->valid_until !== null
            && $this->valid_until->endOfDay()->isPast();
    }

    /** Visible to the customer at all. A draft is not — that is what draft means. */
    public function isVisibleToCustomer(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }
}
