<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\Gateway;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;

/** The admin's drag order for payment gateways, applied wherever gateways are listed. */
class GatewayOrder
{
    /** @param array<int, string|int> $ids gateway ids, first to last */
    public static function save(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            Meta::put((new Gateway)->forceFill(['id' => (int) $id]), 'sort', $position + 1);
        }
    }

    /**
     * @param  array<int, Gateway>|\Illuminate\Support\Collection<int, Gateway>  $gateways
     * @return array<int, Gateway>
     */
    public static function sort(iterable $gateways): array
    {
        $order = Meta::query()
            ->where('model_type', Gateway::class)
            ->where('key', 'sort')
            ->pluck('value', 'model_id');

        $gateways = collect($gateways)->values()->all();

        usort($gateways, fn (Gateway $a, Gateway $b) => (int) ($order[$a->id] ?? PHP_INT_MAX) <=> (int) ($order[$b->id] ?? PHP_INT_MAX));

        return $gateways;
    }
}
