{{-- View Available Addons — what each active service can be extended with. Every action
     hands off to core's upgrade flow, so pricing and proration stay in one place. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.addons') }}</h1>
        <span>{{ __('clienttools.addons_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.addons') }}
    </div>

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
        <div class="wf-alert wf-alert--info" style="text-align:center">
            {{ __('clienttools.addons_empty') }}
        </div>
    @endforelse
</div>
