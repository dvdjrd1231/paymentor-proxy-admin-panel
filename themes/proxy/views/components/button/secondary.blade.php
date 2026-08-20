{{-- Ghost variant of the brand button (see button/primary). --}}
<button {{ $attributes->merge(['class' => 'wf-btn wf-btn--ghost']) }}>
    @if (isset($type) && $type === 'submit')
        <span role="status" wire:loading class="wf-btn-spin" aria-hidden="true"></span>
        <span wire:loading.remove>{{ $slot }}</span>
    @else
        {{ $slot }}
    @endif
</button>
