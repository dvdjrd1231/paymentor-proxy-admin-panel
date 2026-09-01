<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Helpers\ExtensionHelper;
use App\Models\ConfigOption;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Everything Add New Order needs to offer, price and save a line's Configurable Options —
 * both halves of them: core's own `ConfigOption` tree (admin-managed, WHMCS's "Configurable
 * Options"), and whatever a line's server module contributes through `getCheckoutConfig()`
 * (ProxyPanel's Region among them).
 *
 * Every method here mirrors a corresponding piece of {@see \App\Livewire\Products\Checkout}
 * or {@see \App\Livewire\Cart}, parametrised so the admin form can drive several product
 * lines from one Livewire component instead of one per page. The defaulting, pricing,
 * validation and persistence rules are copied rather than reinvented, so an admin-placed
 * order behaves exactly like a customer-placed one downstream — same tables, same shapes.
 */
class ProductConfig
{
    /** Core's Configurable Options for a product, with their priced children preloaded. */
    public static function configOptions(?int $productId): Collection
    {
        if (!$productId) {
            return collect();
        }

        $product = Product::find($productId);

        return $product?->configOptions()->with('children')->get() ?? collect();
    }

    /**
     * A line's server-side checkout fields — ProxyPanel's Region among them. Empty for a
     * product with no server, or whose server offers none. `$values` seeds the call so a
     * module that depends on the current selection (ProxyPanel's live capacity) sees it.
     */
    public static function checkoutConfig(?int $productId, array $values = []): array
    {
        if (!$productId) {
            return [];
        }

        $product = Product::find($productId);

        return $product ? ExtensionHelper::getCheckoutConfig($product, $values) : [];
    }

    /**
     * A freshly picked product's defaults — {@see \App\Livewire\Products\Checkout::mount()}'s
     * defaulting, copied: text/number start empty, checkbox starts off, select/radio starts
     * on the first child. Existing values (switching plans, not products) are kept as-is.
     *
     * @return array<int, mixed>
     */
    public static function defaultConfigOptions(Collection $options, array $existing): array
    {
        return $options->mapWithKeys(function (ConfigOption $option) use ($existing) {
            if (in_array($option->type, ['text', 'number'], true)) {
                return [$option->id => $existing[$option->id] ?? null];
            }

            if ($option->type === 'checkbox') {
                return [$option->id => (bool) ($existing[$option->id] ?? false)];
            }

            return [$option->id => $existing[$option->id] ?? $option->children->first()?->id];
        })->all();
    }

    /**
     * A freshly picked product's checkout-field defaults, mirroring the same mount() method:
     * a select/radio starts on its declared default or its first option; everything else on
     * its declared default or null.
     *
     * @return array<string, mixed>
     */
    public static function defaultCheckoutConfig(array $fields, array $existing): array
    {
        $values = $existing;

        foreach ($fields as $field) {
            if (array_key_exists($field['name'], $values)) {
                continue;
            }

            $values[$field['name']] = in_array($field['type'] ?? null, ['select', 'radio'], true)
                ? ($field['default'] ?? array_key_first($field['options'] ?? []))
                : ($field['default'] ?? null);
        }

        return $values;
    }

    /**
     * The price a line's selected options add to its plan — a checkbox's child if ticked, a
     * select/radio's chosen child, nothing for text/number. Copied from
     * {@see \App\Livewire\Products\Checkout::updatePricing()} and
     * {@see \App\Models\CartItem::price()}, which agree with each other.
     *
     * @return array{price: float, setup_fee: float}
     */
    public static function priceDelta(Collection $options, array $selected, Plan $plan): array
    {
        $price = 0.0;
        $setupFee = 0.0;

        foreach ($options as $option) {
            if ($option->type === 'checkbox') {
                if ($selected[$option->id] ?? false) {
                    $child = $option->children->first();
                    $price += (float) ($child?->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->price ?? 0);
                    $setupFee += (float) ($child?->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->setup_fee ?? 0);
                }

                continue;
            }

            if (in_array($option->type, ['text', 'number'], true)) {
                continue;
            }

            $child = $option->children->firstWhere('id', $selected[$option->id] ?? null);
            $price += (float) ($child?->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->price ?? 0);
            $setupFee += (float) ($child?->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->setup_fee ?? 0);
        }

        return ['price' => $price, 'setup_fee' => $setupFee];
    }

    /**
     * Validation rules for one line's options, prefixed onto the Livewire property path they
     * bind to. Copied from {@see \App\Livewire\Products\Checkout::rules()}.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Collection $options, array $checkoutFields, string $prefix): array
    {
        $rules = [];

        foreach ($options as $option) {
            if (in_array($option->type, ['text', 'number'], true)) {
                $rules["{$prefix}.configOptions.{$option->id}"] = ['required'];
            } elseif ($option->type !== 'checkbox') {
                $rules["{$prefix}.configOptions.{$option->id}"] = ['required', Rule::in($option->children->pluck('id')->all())];
            }
        }

        foreach ($checkoutFields as $field) {
            $line = [];

            if ($field['required'] ?? false) {
                $line[] = 'required';
            }

            switch ($field['type'] ?? null) {
                case 'text':
                case 'number':
                    $line[] = 'string';

                    break;
                case 'select':
                case 'radio':
                    $line[] = 'in:' . implode(',', array_keys($field['options'] ?? []));

                    break;
                case 'checkbox':
                    $line[] = 'nullable';
                    $line[] = 'boolean';

                    break;
            }

            if (isset($field['validation'])) {
                $line = array_merge($line, is_array($field['validation']) ? $field['validation'] : explode('|', $field['validation']));
            }

            if ($line !== []) {
                $rules["{$prefix}.checkoutConfig.{$field['name']}"] = $line;
            }
        }

        return $rules;
    }

    /** Attribute labels for the rules above, so a validation error names the field. */
    public static function attributes(Collection $options, array $checkoutFields, string $prefix): array
    {
        $attributes = [];

        foreach ($options as $option) {
            $attributes["{$prefix}.configOptions.{$option->id}"] = $option->name;
        }

        foreach ($checkoutFields as $field) {
            $attributes["{$prefix}.checkoutConfig.{$field['name']}"] = $field['label'] ?? $field['name'];
        }

        return $attributes;
    }

    /**
     * "» Region: United States - Kansas City" — the reference's annotation under an Order
     * Summary line, one per option that has a value worth showing.
     *
     * @return array<int, string>
     */
    public static function summaryLines(Collection $options, array $selected, array $checkoutFields, array $checkoutValues): array
    {
        $lines = [];

        foreach ($options as $option) {
            if ($option->type === 'checkbox') {
                if ($selected[$option->id] ?? false) {
                    $lines[] = $option->name;
                }

                continue;
            }

            if (in_array($option->type, ['text', 'number'], true)) {
                $value = trim((string) ($selected[$option->id] ?? ''));
                if ($value !== '') {
                    $lines[] = $option->name . ': ' . $value;
                }

                continue;
            }

            $child = $option->children->firstWhere('id', $selected[$option->id] ?? null);
            if ($child) {
                $lines[] = $option->name . ': ' . $child->name;
            }
        }

        foreach ($checkoutFields as $field) {
            $value = $checkoutValues[$field['name']] ?? null;

            // A select/radio's own placeholder ("Select Geographic Region…") is itself one
            // of its options — an empty string is a real, shown selection for those two
            // types, exactly as the reference shows it before a region is actually picked.
            // Anything else showing nothing chosen (a blank text field) stays silent.
            if ($value === null) {
                continue;
            }
            if ($value === '' && !in_array($field['type'] ?? null, ['select', 'radio'], true)) {
                continue;
            }

            $label = in_array($field['type'] ?? null, ['select', 'radio'], true)
                ? ($field['options'][$value] ?? $value)
                : $value;

            $lines[] = ($field['label'] ?? $field['name']) . ': ' . strip_tags((string) $label);
        }

        return $lines;
    }

    /**
     * Saves a line's options onto the service just created for it — the exact split
     * {@see \App\Livewire\Cart::checkout()} uses: a core option becomes a `ServiceConfig`
     * row (or, for text/number, a service property keyed by its env variable), a server
     * checkout field becomes a service property keyed by its own name.
     */
    public static function persist(Service $service, Collection $options, array $selected, array $checkoutFields, array $checkoutValues): void
    {
        foreach ($options as $option) {
            if (in_array($option->type, ['text', 'number'], true)) {
                $value = $selected[$option->id] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $service->properties()->updateOrCreate(
                    ['key' => $option->env_variable ?: $option->name],
                    ['name' => $option->name, 'value' => $value],
                );

                continue;
            }

            if ($option->type === 'checkbox') {
                if (!($selected[$option->id] ?? false)) {
                    continue;
                }

                $service->configs()->create([
                    'config_option_id' => $option->id,
                    'config_value_id' => $option->children->first()?->id,
                ]);

                continue;
            }

            $childId = $selected[$option->id] ?? null;
            if ($childId === null) {
                continue;
            }

            $service->configs()->create(['config_option_id' => $option->id, 'config_value_id' => $childId]);
        }

        foreach ($checkoutFields as $field) {
            $value = $checkoutValues[$field['name']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $service->properties()->updateOrCreate(['key' => $field['name']], ['value' => $value]);
        }
    }
}
