<?php

namespace Paymenter\Extensions\Others\Affiliates\Models;

use App\Helpers\ExtensionHelper;
use App\Models\Order;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateOrder extends Model
{
    protected $table = 'ext_affiliate_orders';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'affiliate_id',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the earnings made from this order
     *
     * @return string
     */
    public function earnings(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $earnings = [];

                /** @var Collection */
                $invoices = $this->order->invoices;
                $extension = ExtensionHelper::getExtension('other', 'Affiliates');
                $reward_percentage = $this->affiliate->reward ?: $extension->config('default_reward');
                // The reference's "Pay One Time Only": {@see RewardAffiliate} credits only
                // the first paid invoice for a one-time-only affiliate, so the earnings
                // shown here must stop at that same one or the balance would claim more
                // than was ever actually credited.
                $oneTimeOnly = (bool) ($this->affiliate->one_time_only ?? false);
                $counted = false;

                $invoices->sortBy('id')->each(function ($invoice) use (&$earnings, &$counted, $reward_percentage, $oneTimeOnly) {
                    if ($invoice->status !== 'paid') {
                        return;
                    }
                    if ($oneTimeOnly && $counted) {
                        return;
                    }
                    $counted = true;
                    if (!isset($earnings[$invoice->currency->name])) {
                        $earnings[$invoice->currency->name] = 0;
                    }
                    $earnings[$invoice->currency->name] += $invoice->total * $reward_percentage / 100;
                });

                foreach ($earnings as $currency => $total) {
                    // Round to 2 decimal places
                    $earnings[$currency] = round($total, 2);
                }

                return $earnings;
            },
        );
    }
}
