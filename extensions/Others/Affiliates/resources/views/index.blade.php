{{-- Affiliate area, in the portal's page/panel chrome. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('affiliates::affiliate.affiliate') }}</h1>
    </div>

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
        <div class="wf-panel">
            <div class="wf-panel-heading">{{ __('affiliates::affiliate.signup-for-affiliate') }}</div>
            <div class="wf-panel-body">
                <p class="wf-muted">{{ __('affiliates::affiliate.you-havent-signed-up-yet') }}</p>

                <form wire:submit.prevent="signup" method="POST">
                    @if ($signup_type === 'custom')
                        <div class="wf-field">
                            <label for="referral_code">{{ __('affiliates::affiliate.code') }}</label>
                            <input id="referral_code" type="text" class="wf-input" wire:model="referral_code" required>
                            @error('referral_code') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <button type="submit" class="wf-btn">{{ __('auth.sign_up') }}</button>
                </form>
            </div>
        </div>
    @endisset
</div>
