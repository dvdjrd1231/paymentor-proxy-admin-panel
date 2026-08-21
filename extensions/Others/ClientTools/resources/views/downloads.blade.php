{{-- Downloads — setup guides, proxy configuration files and tooling, grouped by category
     as the reference portal groups them. The Support rail sits alongside, as on the other
     help pages. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.downloads') }}</h1>
        <span>{{ __('clienttools.downloads_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.downloads') }}
    </div>

    <div class="wf-layout">
        <div>
            <x-support-rail active="downloads" />
        </div>

        <div>
            @forelse ($groups as $category => $items)
                <div class="wf-panel">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-download-2-line /></span>{{ $category }}</span>
                    </div>

                    @foreach ($items as $download)
                        <div class="wf-list-row">
                            <div class="wf-row-main">
                                <div class="wf-list-title">{{ $download->title }}</div>
                                @if ($download->description)
                                    <span class="wf-list-sub">{{ $download->description }}</span>
                                @endif
                            </div>
                            <div class="wf-actions">
                                <button type="button" class="wf-btn wf-btn--sm"
                                        wire:click="download({{ $download->id }})">
                                    {{ __('clienttools.download') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="wf-alert wf-alert--info" style="text-align:center">
                    {{ __('clienttools.downloads_empty') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
