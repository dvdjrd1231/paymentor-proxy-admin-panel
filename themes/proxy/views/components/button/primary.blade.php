{{--
    Overrides the default theme's Tailwind button so the pages that are still rendered
    by the default theme (security, credits, payment methods, 2FA, password reset,
    error pages, payment modal…) use the same brand button as the converted pages.

    Keeps the default's submit spinner contract: `wire:loading` swaps the label for a
    spinner, so forms still show progress.
--}}
<button {{ $attributes->merge(['class' => 'wf-btn']) }}>
    @if (isset($type) && $type === 'submit')
        <span role="status" wire:loading class="wf-btn-spin" aria-hidden="true"></span>
        <span wire:loading.remove>{{ $slot }}</span>
    @else
        {{ $slot }}
    @endif
</button>
