<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Server;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Create a New Product screen (Leandro, 2026-09-07, screenshot of
 * `configproducts.php?action=create`): group, name, URL, module and hidden, then Continue
 * into the product's own page for pricing and the rest.
 *
 * Continue is the reference's own shape and the right one here too — a product is not
 * finished until it has a plan and a price, and those live on core's product editor. This
 * screen creates the row and hands over.
 *
 * ## Product Type
 *
 * The reference opens with four tiles — Shared Hosting, Reseller Hosting, Server/VPS,
 * Other. Paymenter has no type column and no behaviour hanging off one: what a product
 * *does* comes entirely from the server module it provisions through, which is the Module
 * field. The type is what the catalogue's Type column *reads*, though — Leandro's own
 * WHMCS prints "Other (ProxyPanel)" on every row of this store — so it is stored per
 * product in `ext_ao_meta` and shown there.
 *
 * It is presentation, and only presentation. Nothing branches on it, which is why the
 * tiles carry no promise of changing the fields below them the way the reference's do.
 */
class CreateProduct extends Page
{
    protected string $view = 'adminops::pages.create-product';

    protected static ?string $slug = 'create-product';

    /** Navigation is built by {@see WhmcsNavigation}; this is reached from Products/Services. */
    protected static bool $shouldRegisterNavigation = false;

    /** The reference's Product Type tiles — see the class docblock. */
    public string $type = 'other';

    public ?int $categoryId = null;

    public string $name = '';

    public string $slugValue = '';

    public ?int $serverId = null;

    public bool $hidden = false;

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
        return 'Create a New Product';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Pre-selected the way the reference pre-selects the group you came from.
        $this->categoryId = Category::orderBy('sort')->orderBy('id')->value('id');
    }

    public function updatedName(): void
    {
        $this->slugValue = (string) Str::slug($this->name);
    }

    public function create(): void
    {
        $this->validate([
            'categoryId' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'slugValue' => 'nullable|string|max:255|unique:products,slug',
            'serverId' => 'nullable|exists:servers,id',
            'type' => 'required|in:' . implode(',', array_keys(Meta::PRODUCT_TYPES)),
        ], attributes: [
            'categoryId' => 'product group',
            'name' => 'product name',
            'slugValue' => 'URL',
            'serverId' => 'module',
            'type' => 'product type',
        ]);

        $product = Product::create([
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'slug' => $this->slugValue ?: Str::slug($this->name),
            'server_id' => $this->serverId,
            'hidden' => $this->hidden,
        ]);

        Meta::put($product, 'type', $this->type);

        Notification::make()->title('Product created')
            ->body('Add its plan and pricing here to finish it.')->success()->send();

        // Continue »: straight into the product's own editor, where pricing lives.
        $this->redirect(ProductResource::getUrl('edit', ['record' => $product]));
    }

    protected function getViewData(): array
    {
        return [
            'groups' => Category::orderBy('name')->get(['id', 'name']),
            'servers' => Server::orderBy('name')->get(['id', 'name', 'extension']),
            'urlPrefix' => rtrim((string) config('app.url'), '/') . '/store/',
            'cancelUrl' => Catalogue::getUrl(),
        ];
    }
}
