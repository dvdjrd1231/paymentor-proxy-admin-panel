<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Presentation attributes for core records this extension needs to decorate — a product
 * group's headline, tagline and hidden flag, a product's WHMCS-style type.
 *
 * See the `ext_ao_meta` migration for why this exists rather than core's `properties`
 * table. Read through {@see static::for()} rather than querying directly: it takes the
 * whole bag for one record in a single query, which is what the catalogue needs when it
 * draws a hundred rows.
 */
class Meta extends Model
{
    protected $table = 'ext_ao_meta';

    protected $fillable = ['model_type', 'model_id', 'key', 'value'];

    /** The reference's four product types. "Other" is what every product on this install is. */
    public const PRODUCT_TYPES = [
        'shared-hosting' => 'Shared Hosting',
        'reseller-hosting' => 'Reseller Hosting',
        'server-vps' => 'Server/VPS',
        'other' => 'Other',
    ];

    /**
     * Every stored value for one record, keyed.
     *
     * @return array<string, string|null>
     */
    public static function for(Model $record): array
    {
        return static::query()
            ->where('model_type', $record::class)
            ->where('model_id', $record->getKey())
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Every stored value for a whole collection, as [model_id => [key => value]].
     *
     * One query for the page rather than one per row — the catalogue draws every product
     * in the store, and a per-row lookup here is how a list page quietly becomes an N+1.
     *
     * @param  iterable<int, Model>  $records
     * @return array<int, array<string, string|null>>
     */
    public static function forMany(string $type, iterable $records): array
    {
        $ids = collect($records)->map(fn (Model $r) => $r->getKey())->all();

        if ($ids === []) {
            return [];
        }

        $out = [];

        foreach (static::query()->where('model_type', $type)->whereIn('model_id', $ids)->get() as $row) {
            $out[$row->model_id][$row->key] = $row->value;
        }

        return $out;
    }

    /** Write one value, or delete it when blank so the table keeps only what is set. */
    public static function put(Model $record, string $key, mixed $value): void
    {
        $where = ['model_type' => $record::class, 'model_id' => $record->getKey(), 'key' => $key];

        if ($value === null || $value === '' || $value === false) {
            static::query()->where($where)->delete();

            return;
        }

        static::query()->updateOrCreate($where, ['value' => is_bool($value) ? '1' : (string) $value]);
    }
}
