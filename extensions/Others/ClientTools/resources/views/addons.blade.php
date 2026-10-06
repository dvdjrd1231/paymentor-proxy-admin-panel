{{-- Product Addons — what each active service can be extended with. Every action hands off
     to core's upgrade flow, so pricing and proration stay in one place.

     Laid out as the reference lays it out (Leandro, 2026-10-06): the store rail on the left
     with Product Addons marked as the page you are on, the title in the column beside it
     rather than across the top, and — when there is nothing to offer — an amber notice with
     a way back, instead of a pink band that reads as an error. --}}
<div class="wf-page">
    <div class="wf-layout">
        <x-store-rail />

        <div>
            {{-- "Product Addons", the name the rail and the menu use for this page. The
                 reference carries no tagline or breadcrumb here. --}}
            <div class="wf-title">
                <h1>{{ __('clienttools.addons_short') }}</h1>
            </div>
            <hr class="wf-title-rule">

            @forelse ($rows as $row)
                <div class="wf-panel">
                    <div class="wf-panel-heading">
                        <span>
                            <span class="wf-head-icon"><x-ri-archive-stack-fill /></span>
                            {{ $row['service']->product->name ?? __('clienttools.addons_service') }}
                        </span>
                        <a class="wf-btn wf-btn--sm" href="{{ route('services.show', $row['service']) }}" wire:navigate>
                            {{ __('theme.view_more') }}
                        </a>
                    </div>

                    @foreach ($row['upgrades'] as $upgrade)
                        <div class="wf-list-row">
                            <div class="wf-row-main">
                                <div class="wf-list-title">{{ $upgrade->name }}</div>
                                @if ($upgrade->category)
                                    <span class="wf-list-sub">{{ $upgrade->category->name }}</span>
                                @endif
                            </div>
                            <div class="wf-actions">
                                <a class="wf-btn wf-btn--sm"
                                   href="{{ route('services.upgrade', $row['service']) }}" wire:navigate>
                                    {{ __('clienttools.addons_order') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="wf-alert wf-alert--warning" style="text-align:center">
                    {{ __('clienttools.addons_empty') }}
                </div>

                {{-- The reference offers the way back from a page with nothing on it. --}}
                <div style="text-align:center; margin-top:1.25rem">
                    <a class="wf-btn" href="{{ route('dashboard') }}" wire:navigate>
                        &larr; {{ __('clienttools.addons_return') }}
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</div>
