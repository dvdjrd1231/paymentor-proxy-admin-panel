{{-- Set IP Rotation time — the reference's sidebar page of the same name.

     No panel chrome: a plain brand heading, the explanation in a tinted band, then a
     full-width field and a full-width button, which is how the reference lays it out
     (Leandro, 2026-10-07). The API key lives on its own rail entry, not here. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.rotation') }}</h2>

    <div class="wf-alert wf-alert--notice wf-action-note">{{ __('proxypanel.rotation_time_hint') }}</div>

    @if ($maxRotate)
        <p class="wf-section-note">
            {{ __('proxypanel.rotations_used') }}: {{ $rotationCounter ?? 0 }} / {{ $maxRotate }}
        </p>
    @endif

    @if ($canChangeRotation)
        <form method="POST" action="{{ route('extensions.servers.proxypanel.rotation', $service) }}">
            @csrf
            <input class="wf-input wf-input--block" type="number" id="minutes" name="minutes"
                   min="0" max="10080" value="{{ old('minutes', $rotationTime ?? 0) }}"
                   placeholder="{{ __('proxypanel.rotation_placeholder') }}"
                   aria-label="{{ __('proxypanel.rotation_placeholder') }}">
            <button type="submit" class="wf-btn wf-btn--block wf-action-go">
                {{ __('proxypanel.rotation_save') }}
            </button>
        </form>
    @else
        <p class="wf-section-note">{{ __('proxypanel.rotation_change_not_allowed') }}</p>
    @endif
</div>
