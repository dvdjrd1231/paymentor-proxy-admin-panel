{{-- Rotate NOW! — the reference's rotate.tpl: it says what rotating does, asks whether the
     customer is sure, and only then offers the button. Reaching this page rotates nothing.

     When the product forbids rotation the page still renders and the button is withheld,
     which is what the reference's `{if $enabled}` does — the customer is told why instead
     of finding the entry missing. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.rotate_title') }}</h2>

    <div class="wf-alert wf-alert--notice wf-action-note">{{ __('proxypanel.rotate_warning') }}</div>

    @if ($maxRotate)
        <p class="wf-section-note">
            {{ __('proxypanel.rotations_used') }}: {{ $rotationCounter ?? 0 }} / {{ $maxRotate }}
        </p>
    @endif

    @if ($canRotate)
        <form method="POST" action="{{ route('extensions.servers.proxypanel.rotate', $service) }}">
            @csrf
            <button type="submit" class="wf-btn wf-btn--block wf-action-go">
                {{ __('proxypanel.action_rotate_confirm') }}
            </button>
        </form>
    @else
        <p class="wf-section-note">{{ __('proxypanel.rotate_not_allowed') }}</p>
    @endif
</div>
