{{-- Set IP Rotation time — the reference's sidebar page of the same name. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('proxypanel.rotation') }}</div>
        <div class="wf-panel-body">
            @if ($maxRotate)
                <p class="wf-section-note">
                    {{ __('proxypanel.rotations_used') }}: {{ $rotationCounter ?? 0 }} / {{ $maxRotate }}
                </p>
            @endif

            @if ($canChangeRotation)
                <form method="POST" action="{{ route('extensions.servers.proxypanel.rotation', $service) }}">
                    @csrf
                    <div class="wf-field">
                        <label for="minutes">{{ __('proxypanel.rotation_time') }}</label>
                        <input class="wf-input" type="number" id="minutes" name="minutes"
                               min="0" max="10080" value="{{ old('minutes', $rotationTime ?? 0) }}">
                        <span class="wf-section-note">{{ __('proxypanel.rotation_time_hint') }}</span>
                    </div>
                    <div class="wf-actions">
                        <button type="submit" class="wf-btn wf-btn--sm">{{ __('proxypanel.save') }}</button>
                    </div>
                </form>
            @else
                <p class="wf-section-note">{{ __('proxypanel.rotation_change_not_allowed') }}</p>
            @endif
        </div>
    </div>

    @if ($apiKey)
        <div class="wf-panel">
            <div class="wf-panel-heading">{{ __('proxypanel.api_key') }}</div>
            <div class="wf-panel-body">
                <code class="wf-kv-value">{{ $apiKey }}</code>
            </div>
        </div>
    @endif
</div>
