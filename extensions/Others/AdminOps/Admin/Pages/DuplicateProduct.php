<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ProductResource;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Duplicate a Product screen (Leandro, 2026-09-07, screenshot of
 * `configproducts.php?action=duplicate`): pick the product, name the copy, Continue.
 *
 * This was an inline panel on the catalogue that always named the copy "<name> (Copy)".
 * The reference asks for the name up front, which is better: the copy usually exists to
 * become a specific thing — the weekly version of a monthly plan — and renaming it
 * afterwards on another screen is a step nobody should have to remember.
 *
 * A full copy: the product row, its plans and their prices, its settings, and its
 * configurable-option links. `sort` is deliberately not copied — the copy goes to the end
 * of its group rather than sharing a position with the original.
 */
class DuplicateProduct extends Page
{
    protected string $view = 'adminops::pages.duplicate-product';

    protected static ?string $slug = 'duplicate-product';

    /** Navigation is built by {@see WhmcsNavigation}; this is reached from Products/Services. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $sourceId = null;

    public string $name = '';

    public static function canAccess(): bool
    {
        return ProductResource::canCreate();
    }

    public function getTitle(): string
    {
        return 'Products/Services';
    }

    public function getHeading(): string
    {
        return 'Products/Services';
    }

    public function getSubheading(): ?string
    {
        return 'Duplicate a Product';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->sourceId = Product::orderBy('name')->value('id');
    }

    public function duplicate(): void
    {
        $this->validate([
            'sourceId' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
        ], attributes: ['sourceId' => 'existing product', 'name' => 'new product name']);

        $source = Product::with(['plans.prices', 'settings'])->findOrFail($this->sourceId);

        $copy = DB::transaction(function () use ($source): Product {
            $copy = $source->replicate(['sort']);
            $copy->name = $this->name;
            $copy->slug = Str::slug($this->name) . '-' . dechex(crc32($source->id . microtime()));
            $copy->save();

            foreach ($source->plans as $plan) {
                $planCopy = $plan->replicate();
                $planCopy->priceable_id = $copy->id;
                $planCopy->save();

                foreach ($plan->prices as $price) {
                    $priceCopy = $price->replicate();
                    $priceCopy->plan_id = $planCopy->id;
                    $priceCopy->save();
                }
            }

            foreach ($source->settings as $setting) {
                $settingCopy = $setting->replicate();
                $settingCopy->settingable_id = $copy->id;
                $settingCopy->save();
            }

            DB::table('config_option_products')
                ->where('product_id', $source->id)
                ->get()
                ->each(fn ($link) => DB::table('config_option_products')->insert([
                    'config_option_id' => $link->config_option_id,
                    'product_id' => $copy->id,
                ]));

            return $copy;
        });

        Notification::make()->title('Product duplicated')
            ->body('"' . $copy->name . '" sits in the same group, with the original\'s plans and pricing.')
            ->success()->send();

        $this->redirect(ProductResource::getUrl('edit', ['record' => $copy]));
    }

    protected function getViewData(): array
    {
        return [
            // Grouped the way the reference labels them: "Shared Hosting - Demo Plan".
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'cancelUrl' => Catalogue::getUrl(),
        ];
    }
}
