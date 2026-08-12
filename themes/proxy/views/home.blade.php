{{-- Storefront home — WHMCS "Six" style. The intro copy is the theme's `home_page_text`
     setting (Admin → Settings → Theme), so it is editable without touching code. --}}
<div>
    <div class="wf-hero">
        <div class="wf-shell">
            <article class="prose dark:prose-invert max-w-full">
                {!! Str::markdown(theme('home_page_text', 'Welcome to Paymenter'), [
                    'allow_unsafe_links' => false,
                    'renderer' => ['soft_break' => '<br>'],
                ]) !!}
            </article>
        </div>
    </div>

    <div class="wf-page">
        <div class="wf-section">{{ __('navigation.services') }}</div>

        <div class="wf-cards">
            @forelse ($categories as $category)
                <div class="wf-card">
                    <div class="wf-card-head">{{ $category->name }}</div>
                    <div class="wf-card-body">
                        @if ($category->image)
                            <img src="{{ Storage::url($category->image) }}" alt="{{ $category->name }}" class="wf-card-img">
                        @endif
                        @if(theme('show_category_description', true))
                            <article class="prose dark:prose-invert">{!! $category->description !!}</article>
                        @endif
                    </div>
                    <div class="wf-card-foot">
                        <a class="wf-btn wf-btn--sm wf-btn--block" wire:navigate
                            href="{{ route('category.show', ['category' => $category->slug]) }}">
                            {{ __('common.button.view_all') }}
                        </a>
                    </div>
                </div>
            @empty
                <div class="wf-empty">{{ __('product.no_products') ?? 'Nothing available yet.' }}</div>
            @endforelse
        </div>
    </div>

    {!! hook('pages.home') !!}
</div>
