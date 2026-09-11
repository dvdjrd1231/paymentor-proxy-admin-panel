<?php

namespace Paymenter\Extensions\Others\Affiliates\Listeners;

use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Support\Collection;
use Paymenter\Extensions\Others\Affiliates\Models\Affiliate;
use Paymenter\Extensions\Others\Affiliates\Models\AffiliateOrder;

class RewardAffiliate
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        /**
         * @var Invoice $invoice
         */
        $invoice = $event->invoice;

        if ($invoice->items()->first()->reference_type !== Service::class) {
            return;
        }
        $order = $invoice->items()->first()->reference->order;
        if (!$order) {
            return;
        }
        $referral = AffiliateOrder::where('order_id', $order->id)->first();

        if (!$referral) {
            return;
        }

        /**
         * @var Affiliate $affiliate
         */
        $affiliate = $referral->affiliate;

        // The reference's "Pay One Time Only": once this order has already paid a first
        // invoice, a later renewal earns nothing more. Checked here rather than at display
        // time, because {@see AffiliateOrder::earnings()} sums every paid invoice for the
        // credits page and must keep matching what was actually credited.
        if ($affiliate->one_time_only && $order->invoices()
            ->where('status', 'paid')->where('id', '!=', $invoice->id)->exists()) {
            return;
        }

        $extension = ExtensionHelper::getExtension('other', 'Affiliates');
        $reward_percentage = $affiliate->reward ?: $extension->config('default_reward');
        $reward_amount = static::rewardFor($invoice, (float) $reward_percentage);

        /**
         * @var Collection
         */
        $user_credits = $affiliate->user->credits;
        $affiliate_credits = $user_credits->filter(function ($credit) use ($invoice) {
            return $credit->currency_code === $invoice->currency_code;
        })->first();

        if ($affiliate_credits) {
            // Add reward to credits
            $affiliate->user->credits()->where('currency_code', $invoice->currency_code)->update([
                'amount' => $affiliate_credits->amount + $reward_amount,
            ]);
        } else {
            // Create new credits with the invoice's currency code
            $affiliate->user->credits()->create([
                'amount' => $reward_amount,
                'currency_code' => $invoice->currency_code,
            ]);
        }
    }

    /** What this invoice earns, honouring each product's Custom Affiliate Payout. */
    public static function rewardFor($invoice, float $defaultPercentage): float
    {
        $items = $invoice->items ?? collect();

        // No line detail to work from: the whole-invoice percentage is all there is.
        if ($items->isEmpty()) {
            return (float) $invoice->total * $defaultPercentage / 100;
        }

        $total = 0.0;

        foreach ($items as $item) {
            $line = (float) $item->price * max(1, (int) ($item->quantity ?: 1));
            $product = static::productFor($item);

            $meta = $product
                ? \Paymenter\Extensions\Others\AdminOps\Models\Meta::for($product)
                : [];

            switch ($meta['affiliate_payout'] ?? 'default') {
                case 'none':
                    break;
                case 'percentage':
                    $total += $line * (float) ($meta['affiliate_amount'] ?? 0) / 100;
                    break;
                case 'fixed':
                    $total += (float) ($meta['affiliate_amount'] ?? 0);
                    break;
                default:
                    $total += $line * $defaultPercentage / 100;
            }
        }

        return $total;
    }

    /** The product an invoice line sells, where the line references a service. */
    private static function productFor($item)
    {
        $reference = $item->reference ?? null;

        if ($reference instanceof \App\Models\Service) {
            return $reference->product;
        }

        return null;
    }
}
