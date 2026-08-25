{{--
    Overrides the default theme's payment modal.

    Identical behaviour and identical Livewire calls — only the close trigger is rebuilt,
    because the default's is Tailwind (`flex gap-4`, `text-primary-100`, `size-6`) and this
    theme does not load Tailwind, so the amount due and the close control ran together as
    two unstyled lines.

    `$this->pay` is whatever the gateway handed back to render in place (a card form, a
    crypto QR, a redirect notice). A gateway that returns a URL never reaches here — the
    component redirects to it — so this is the on-page payment surface and nothing else may
    take its place.
--}}
<x-modal
    :title="config('settings.invoice_proforma', false)
        ? __('invoices.payment_for_proforma_invoice', ['id' => $invoice->id])
        : __('invoices.payment_for_invoice', ['number' => $invoice->number])"
    open
>
    <x-slot name="closeTrigger">
        <div class="wf-modal-head-right">
            <span class="wf-modal-due">{{ __('invoices.amount_due', ['amount' => $invoice->formattedRemaining]) }}</span>
            {{-- Closing is `exitPay()` alone, without the default's companion
                 `@click="open = false"`. That pair is wrong: `wire:confirm` blocks the
                 Livewire call but not the Alpine handler, so declining the confirmation
                 still hid the modal — and since `?pay=1` was untouched it was neither open
                 nor cancelled. exitPay() redirects to the invoice without `?pay=1`, which
                 closes it properly, and declining now leaves it alone. --}}
            <button type="button" class="wf-modal-close" wire:confirm="{{ __('Are you sure?') }}"
                wire:click="exitPay" aria-label="{{ __('Close') }}">&times;</button>
        </div>
    </x-slot>

    @if ($this->pay)
        <div class="wf-pay-embed">{{ $this->pay }}</div>
    @else
        @include('invoices.partials.payment-options')
    @endif
</x-modal>
