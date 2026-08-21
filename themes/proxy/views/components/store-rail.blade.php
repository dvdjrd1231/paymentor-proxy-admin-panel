{{--
    The storefront's left rail — Categories + Actions — which the reference portal shows
    on every shop page, including the cart. Shared so the cart and the category pages
    cannot drift apart.

    Usage: <x-store-rail :active="$category ?? null" />

    The category query matches App\Classes\Navigation::getLinks() exactly (root
    categories that have children or at least one non-hidden product), so the rail and
    the Store ▾ menu always list the same things.
--}}
@props(['active' => null])

@php
    use App\Models\Category;

    $railCategories = Category::whereNull('parent_id')
        ->where(function ($query) {
            $query->whereHas('children')
                ->orWhereHas('products', fn ($q) => $q->where('hidden', false));
        })
        ->orderBy('sort')
        ->get();

    $activeId = is_object($active) ? $active->id : $active;
@endphp

<div>
    @if ($railCategories->isNotEmpty())
        <div class="wf-panel wf-panel--brand">
            <div class="wf-panel-heading">
                <span class="wf-head-icon"><x-ri-shopping-cart-2-fill /></span>{{ __('theme.categories') }}
                <span class="wf-chevron">▲</span>
            </div>
            <ul class="wf-list">
                @foreach ($railCategories as $railCategory)
                    <li>
                        <a href="{{ route('category.show', ['category' => $railCategory->slug]) }}" wire:navigate
                            class="{{ $activeId == $railCategory->id ? 'is-active' : '' }}">
                            <span>{{ $railCategory->name }}</span>
                        </a>
                    </li>
                @endforeach

                {{-- The reference closes this list with Product Addons, which is not a
                     catalogue category but the page listing what an existing service can be
                     extended with. Guarded on the route, so it disappears with the
                     extension rather than leaving a dead entry. --}}
                @if (Route::has('addons'))
                    <li>
                        <a href="{{ route('addons') }}" wire:navigate
                            class="{{ request()->routeIs('addons') ? 'is-active' : '' }}">
                            <span>{{ __('clienttools.addons_short') }}</span>
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    @endif

    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading">+ {{ __('theme.actions') }}<span class="wf-chevron">▲</span></div>
        <ul class="wf-list">
            <li>
                <a href="{{ route('cart') }}" wire:navigate
                    class="{{ request()->routeIs('cart') ? 'is-active' : '' }}">
                    <span>{{ __('theme.view_cart') }}</span>
                    <span class="wf-head-icon"><x-ri-shopping-cart-2-fill /></span>
                </a>
            </li>
        </ul>
    </div>
</div>
