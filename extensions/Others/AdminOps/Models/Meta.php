<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Presentation attributes for core records this extension needs to decorate — a product
 * group's headline, tagline and hidden flag, a product's WHMCS-style type.
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

    /** The reference's Order Form Template, as the layouts this theme actually renders. */
    public const ORDER_FORMS = [
        'cards' => 'Standard Cards — description beside the price',
        'compact' => 'Compact List — one row per product',
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

    /**
     * The ids of product groups marked hidden.
     *
     * @return array<int, int>
     */
    public static function hiddenCategoryIds(): array
    {
        return static::query()
            ->where('model_type', \App\Models\Category::class)
            ->where('key', 'hidden')
            ->where('value', '1')
            ->pluck('model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * The slugs of product groups marked hidden.
     *
     * @return array<int, string>
     */
    public static function hiddenCategorySlugs(): array
    {
        $ids = static::hiddenCategoryIds();

        return $ids === []
            ? []
            : \App\Models\Category::whereIn('id', $ids)->pluck('slug')->all();
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
