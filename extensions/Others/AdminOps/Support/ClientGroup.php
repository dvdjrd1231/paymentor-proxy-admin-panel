<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * What belonging to a client group actually does (Leandro, 2026-09-07: "these group will
 * this condition as common. this is client group").
 */
class ClientGroup
{
    /** Marks our own line so it can be found and rebuilt without touching anything else. */
    public const MARKER = \Paymenter\Extensions\Others\AdminOps\Models\ClientGroup::class;

    /** The group a client belongs to, or null. */
    public static function forUser(?int $userId): ?object
    {
        if (!$userId || !Schema::hasTable('ext_client_groups')) {
            return null;
        }

        $id = DB::table('properties')
            ->where('model_type', User::class)->where('model_id', $userId)
            ->where('key', 'client_group_id')->value('value');

        return $id ? DB::table('ext_client_groups')->find((int) $id) : null;
    }

    /**
     * Rebuild the discount line for an invoice. Idempotent: the previous line is removed
     * first, so this can run on every item change without compounding.
     *
     * @return float The discount applied, as a positive number.
     */
    public static function applyDiscount(Invoice $invoice): float
    {
        $group = static::forUser($invoice->user_id);

        $invoice->items()->where('reference_type', self::MARKER)->delete();
        $invoice->refresh();
        $invoice->load('items');

        if (!$group || (float) $group->discount_percent <= 0) {
            return 0.0;
        }

        // Charges only. A negative line is somebody else's credit and must not be
        // discounted a second time.
        $subtotal = $invoice->items
            ->filter(fn ($item) => (float) $item->price > 0)
            ->sum(fn ($item) => (float) $item->price * max(1, (int) $item->quantity));

        $discount = round($subtotal * (float) $group->discount_percent / 100, 2);

        if ($discount <= 0) {
            return 0.0;
        }

        $invoice->items()->create([
            'description' => $group->name . ' discount ('
                . rtrim(rtrim(number_format((float) $group->discount_percent, 2), '0'), '.') . '%)',
            'price' => -$discount,
            'quantity' => 1,
            'reference_type' => self::MARKER,
            'reference_id' => $group->id,
        ]);

        $invoice->refresh();

        return $discount;
    }
}
