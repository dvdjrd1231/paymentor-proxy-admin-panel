{{-- Affiliate area, in the portal's page/panel chrome. --}}
<div class="wf-page">
    {{-- Before activation the reference titles the page for the action it wants; after it,
         for the thing being managed. --}}
    <div class="wf-title">
        <h1>{{ isset($affiliate) ? __('affiliates::affiliate.affiliate') : __('theme.activate_affiliate') }}</h1>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>
        <a href="{{ route('account') }}" wire:navigate>{{ __('navigation.account') }}</a>
        <span>/</span>{{ __('affiliates::affiliate.affiliate') }}
    </div>

    @isset($affiliate)
        <div class="wf-stats">
            <div class="wf-stat">
                <div class="wf-stat-head"><span class="wf-stat-num">{{ Number::format($affiliate->visitors) }}</span></div>
                <div class="wf-stat-label">{{ __('affiliates::affiliate.visitors') }}</div>
            </div>
            <div class="wf-stat">
                <div class="wf-stat-head"><span class="wf-stat-num">{{ Number::format($affiliate->signups) }}</span></div>
                <div class="wf-stat-label">{{ __('affiliates::affiliate.signups') }}</div>
            </div>
            <div class="wf-stat">
                <div class="wf-stat-head">
                    <span class="wf-stat-num">
                        @forelse ($affiliate->earnings as $currency => $amount)
                            {{ $currency }} {{ $amount }}@if (!$loop->last)<br>@endif
                        @empty
                            0
                        @endforelse
                    </span>
                </div>
                <div class="wf-stat-label">{{ __('affiliates::affiliate.earnings') }}</div>
            </div>
        </div>

        <div class="wf-panel">
            <div class="wf-panel-heading">{{ __('affiliates::affiliate.your-affiliate-link') }}</div>
            <div class="wf-panel-body">
                <div class="wf-inline-form" x-data="{
                    copy() {
                        navigator.clipboard?.writeText($refs.ref.value);
                        $refs.ref.select();
                    }
                }">
                    <input x-ref="ref" class="wf-input" type="text" readonly
                        value="{{ url('/?ref=' . $affiliate->code) }}">
                    <button type="button" class="wf-btn" x-on:click="copy">
                        {{ __('affiliates::affiliate.copy') }}
                    </button>
                </div>
            </div>
        </div>
    @else
        {{-- Not activated yet. The reference sells the programme before asking for anything:
             a pale band with the pitch, the terms as three plain points, then a single
             centred button — not a form panel headed "sign up". --}}
        <div class="wf-hero">
            <h2>{{ __('theme.affiliate_pitch') }}</h2>
            <p>{{ __('theme.affiliate_pitch_sub') }}</p>
        </div>

        <ul class="wf-facts">
            <li>{{ __('theme.affiliate_point_commission') }}</li>
            <li>{{ __('theme.affiliate_point_cookie', ['days' => config('settings.affiliate_cookie_days', 90)]) }}</li>
            <li>
                {{ __('theme.affiliate_point_more') }}
                @if (Route::has('contact'))
                    <a href="{{ route('contact') }}" wire:navigate>{{ __('sitepages.contact_us') }}</a>.
                @endif
            </li>
        </ul>

        <form wire:submit.prevent="signup" method="POST" class="wf-actions wf-actions--center">
            @if ($signup_type === 'custom')
                {{-- Only shown when the operator requires a custom code; otherwise the
                     button alone activates, as on the reference. --}}
                <div class="wf-field" style="max-width:20rem;margin-inline:auto">
                    <label for="referral_code">{{ __('affiliates::affiliate.code') }}</label>
                    <input id="referral_code" type="text" class="wf-input" wire:model="referral_code" required>
                    @error('referral_code') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            <button type="submit" class="wf-btn wf-btn--lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="signup">{{ __('theme.activate_affiliate') }}</span>
                <span wire:loading wire:target="signup">…</span>
            </button>
        </form>
    @endisset
</div>
