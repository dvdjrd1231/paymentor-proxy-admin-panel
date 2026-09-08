<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Promotions, on Paymenter's coupons.
 *
 * The sidebar had a Promotions entry, but it fell through to core's own Filament resource —
 * a different screen in a different shape from everything around it. This is the reference's:
 * the grid of promotions, and the editor beneath it on the same page.
 *
 * **Only fields the checkout actually enforces are live here.** `App\Classes\Cart::validateCoupon`
 * is the whole of what this platform checks — code, start date, expiry, max uses, max uses per
 * user, and the product restriction — plus `Coupon::calculateDiscount` for type and what it
 * applies to, and `Service`/`CronJob` for how many cycles it survives. The reference's other
 * fields (Requires, Lifetime Promotion, New Signups Only, Upgrade Config) are shown inert with
 * the reason, rather than offered as controls that would accept input and change nothing.
 */
class Promotions extends Page
{
    protected string $view = 'adminops::pages.promotions';

    protected static ?string $slug = 'promotions';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * Which promotion is open in the editor: an id, 'new', or '' for none.
     *
     * Query-stringed so a promotion is a URL — support can paste "the SUMMER25 promotion"
     * into a ticket and it opens on it.
     */
    #[Url(as: 'promo', keep: false)]
    public string $editing = '';

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '', 'type' => 'percentage', 'value' => '', 'applies_to' => 'all',
        'recurring' => '1', 'recurring_n' => '', 'max_uses' => '', 'max_uses_per_user' => '',
        'starts_at' => '', 'expires_at' => '',
    ];

    /** @var array<int, int|string> Products this promotion is limited to; empty means all. */
    public array $productIds = [];

    public ?int $confirming = null;

    /**
     * How long the discount lasts, in billing cycles.
     *
     * Not free text: `Service::240` treats 0 as "every invoice, forever" and N as "the first
     * N", and `Cart` treats null and 1 alike as first-cycle-only. Three named choices say
     * that; a number box would invite someone to type 0 meaning "never".
     */
    public const RECURRING = [
        '1' => 'First payment only',
        '0' => 'Every payment, for the life of the service',
        'n' => 'The first N payments',
    ];

    public const TYPES = ['percentage' => 'Percentage', 'fixed' => 'Fixed Amount'];

    /** What the discount comes off — Paymenter's own split, which WHMCS words differently. */
    public const APPLIES_TO = [
        'all' => 'Recurring price and setup fee',
        'price' => 'Recurring price only',
        'setup_fee' => 'Setup fee only',
    ];

    public static function canAccess(): bool
    {
        return CouponResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Promotions';
    }

    public function getSubheading(): ?string
    {
        return 'Promotional codes allow you to offer discounts on your products. '
            . 'A code is entered by the customer at checkout.';
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel);
    }

    public static function registerNavigation(): void
    {
        WhmcsNavigation::place(CouponResource::class, static::class);
    }

    // ── The editor ──────────────────────────────────────────────────────────────

    /**
     * Land on a promotion when the URL names one.
     *
     * `updatedEditing()` fires on a Livewire update, not on the first render, so without
     * this `?promo=5` would open the editor with an empty form over promotion 5 — and
     * saving it would have overwritten that promotion with blanks.
     */
    public function mount(): void
    {
        if ($this->editing !== '') {
            $this->updatedEditing();
        }
    }

    /** Opening a promotion loads it; 'new' clears the form to the reference's defaults. */
    public function updatedEditing(): void
    {
        $this->resetValidation();
        $this->confirming = null;

        if ($this->editing === 'new' || $this->editing === '') {
            $this->form = [
                'code' => '', 'type' => 'percentage', 'value' => '', 'applies_to' => 'all',
                'recurring' => '1', 'recurring_n' => '', 'max_uses' => '', 'max_uses_per_user' => '',
                'starts_at' => '', 'expires_at' => '',
            ];
            $this->productIds = [];

            return;
        }

        $coupon = Coupon::with('products')->find((int) $this->editing);

        if (!$coupon) {
            $this->editing = '';

            return;
        }

        $this->form = [
            'code' => (string) $coupon->code,
            'type' => (string) $coupon->type,
            'value' => (string) $coupon->value,
            'applies_to' => (string) ($coupon->applies_to ?: 'all'),
            // A stored count other than 0 or 1 is the reference's "for N cycles".
            'recurring' => in_array((string) $coupon->recurring, ['0', '1'], true)
                ? (string) $coupon->recurring
                : ($coupon->recurring === null ? '1' : 'n'),
            'recurring_n' => (string) ($coupon->recurring ?: ''),
            'max_uses' => (string) ($coupon->max_uses ?: ''),
            'max_uses_per_user' => (string) ($coupon->max_uses_per_user ?: ''),
            'starts_at' => $coupon->starts_at?->format('Y-m-d') ?? '',
            'expires_at' => $coupon->expires_at?->format('Y-m-d') ?? '',
        ];

        $this->productIds = $coupon->products->pluck('id')->all();
    }

    public function edit(int $id): void
    {
        $this->editing = (string) $id;
        $this->updatedEditing();
    }

    public function create(): void
    {
        $this->editing = 'new';
        $this->updatedEditing();
    }

    public function cancel(): void
    {
        $this->editing = '';
        $this->updatedEditing();
    }

    public function save(): void
    {
        $existing = $this->editing !== '' && $this->editing !== 'new'
            ? Coupon::find((int) $this->editing)
            : null;

        $this->validate([
            // The code is what a customer types, so it has to be unique — two rows with the
            // same code and different discounts would resolve by whichever came first.
            'form.code' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('coupons', 'code')->ignore($existing?->id),
            ],
            'form.type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'form.applies_to' => 'required|in:' . implode(',', array_keys(self::APPLIES_TO)),
            'form.value' => 'required|numeric|min:0' . ($this->form['type'] === 'percentage' ? '|max:100' : ''),
            'form.recurring' => 'required|in:' . implode(',', array_keys(self::RECURRING)),
            'form.recurring_n' => $this->form['recurring'] === 'n' ? 'required|integer|min:2' : 'nullable',
            'form.max_uses' => 'nullable|integer|min:1',
            'form.max_uses_per_user' => 'nullable|integer|min:1',
            'form.starts_at' => 'nullable|date',
            'form.expires_at' => 'nullable|date|after_or_equal:form.starts_at',
            'productIds' => 'array',
            'productIds.*' => 'integer|exists:products,id',
        ], attributes: [
            'form.code' => 'promotion code',
            'form.value' => 'value',
            'form.recurring_n' => 'number of payments',
            'form.max_uses' => 'maximum uses',
            'form.max_uses_per_user' => 'maximum uses per client',
            'form.expires_at' => 'expiry date',
        ]);

        if ($existing) {
            abort_unless(CouponResource::canEdit($existing), 403);
        } else {
            abort_unless(CouponResource::canCreate(), 403);
        }

        $attributes = [
            'code' => $this->form['code'],
            'type' => $this->form['type'],
            'applies_to' => $this->form['applies_to'],
            'value' => (float) $this->form['value'],
            'recurring' => match ($this->form['recurring']) {
                '0' => 0,
                'n' => (int) $this->form['recurring_n'],
                default => 1,
            },
            'max_uses' => $this->form['max_uses'] === '' ? null : (int) $this->form['max_uses'],
            'max_uses_per_user' => $this->form['max_uses_per_user'] === '' ? null : (int) $this->form['max_uses_per_user'],
            'starts_at' => $this->form['starts_at'] ?: null,
            'expires_at' => $this->form['expires_at'] ?: null,
        ];

        $coupon = $existing ? tap($existing)->update($attributes) : Coupon::create($attributes);

        // Empty means every product, which is what an empty pivot means to validateCoupon.
        $coupon->products()->sync($this->productIds);

        $this->editing = (string) $coupon->id;

        Notification::make()->title($existing ? 'Promotion saved' : 'Promotion created')->success()->send();
    }

    public function delete(): void
    {
        $id = $this->confirming;
        $this->confirming = null;

        $coupon = Coupon::find($id);

        if (!$coupon) {
            return;
        }

        abort_unless(CouponResource::canDelete($coupon), 403);

        // A coupon on a live service is what tells the cron how long the discount lasts —
        // deleting it would quietly put those services back to full price at the next renewal.
        $inUse = $coupon->services()->count();

        if ($inUse > 0) {
            Notification::make()->title('Promotion is in use')
                ->body($inUse . ' ' . str('service')->plural($inUse) . ' still price off this code. Let it expire instead — set an expiry date, and it stops being accepted without changing what those services pay.')
                ->danger()->send();

            return;
        }

        $coupon->delete();

        if ((string) $id === $this->editing) {
            $this->editing = '';
            $this->updatedEditing();
        }

        Notification::make()->title('Promotion deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'promotions' => Coupon::withCount(['products', 'services'])->orderBy('code')->get(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'recurringOptions' => self::RECURRING,
            'types' => self::TYPES,
            'appliesTo' => self::APPLIES_TO,
            'canCreate' => CouponResource::canCreate(),
        ];
    }
}
