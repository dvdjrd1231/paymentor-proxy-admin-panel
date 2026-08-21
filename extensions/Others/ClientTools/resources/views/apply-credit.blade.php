{{-- The reference portal's "Apply Credit" panel: a green band above the invoice items
     stating the balance, with an amount box and one button. Hidden entirely when there is
     no credit to apply, so a paid invoice or an empty balance shows nothing. --}}
@if ($credit && $credit->amount > 0 && $max > 0)
    <div class="wf-panel wf-creditbox">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-wallet-3-fill /></span>{{ __('clienttools.apply_credit') }}</span>
        </div>
        <div class="wf-panel-body">
            <p>
                {{ __('clienttools.credit_balance_is', ['amount' => $credit->formatted_amount]) }}
                {{ __('clienttools.credit_help') }}
            </p>

            <form wire:submit.prevent="apply" class="wf-inline-form">
                <label for="credit-amount" class="sr-only">{{ __('clienttools.credit_amount') }}</label>
                <input id="credit-amount" type="number" step="0.01" min="0.01" max="{{ $max }}"
                       class="wf-input" wire:model="amount" inputmode="decimal">
                <button type="submit" class="wf-btn" wire:loading.attr="disabled">
                    {{ __('clienttools.apply_credit') }}
                </button>
            </form>

            @error('amount') <span class="wf-error">{{ $message }}</span> @enderror
        </div>
    </div>
@endif
