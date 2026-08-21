{{-- My Quotes. Paymenter has no quoting system, so this renders the reference portal's
     empty state rather than presenting real invoices as quotes. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.quotes') }}</h1>
        <span>{{ __('clienttools.quotes_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.quotes') }}
    </div>

    @forelse ($quotes as $quote)
        <div class="wf-panel">
            <div class="wf-panel-heading"><span>{{ $quote->subject }}</span></div>
        </div>
    @empty
        <div class="wf-alert wf-alert--info" style="text-align:center">
            {{ __('clienttools.quotes_empty') }}
        </div>
    @endforelse
</div>
