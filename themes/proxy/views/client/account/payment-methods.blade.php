{{--
    Saved payment methods, reskinned as the reference portal's panels. All Livewire
    bindings (setup modal, createBillingAgreement, cancelSetup, removePaymentMethod)
    are the core component's — only the markup around them changes.
--}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('account.payment_methods') }}</h1>
    </div>

    @if ($setupModalVisible)
        <x-modal :title="__('account.payment_methods')" open="true">
            <x-slot name="closeTrigger">
                <button wire:click="$set('setupModalVisible', false)" class="text-primary-100">
                    <x-ri-close-fill class="size-6" />
                </button>
            </x-slot>

            @if (count($this->gateways) > 1)
                <x-form.select name="gateway" :label="__('account.input.payment_gateway')" wire:model.live="gateway" required>
                    @foreach ($this->gateways as $gateway)
                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                    @endforeach
                </x-form.select>
            @elseif (count($this->gateways) === 0)
                <p class="wf-error">{{ __('account.no_payment_gateways_available') }}</p>
            @endif

            <x-button.primary wire:click="createBillingAgreement" wire:loading.attr="disabled">
                {{ __('account.setup_payment_method') }}
            </x-button.primary>

            @if ($this->setup)
                <x-modal :title="__('account.setup_payment_method')" open>
                    <div class="mt-8">{{ $this->setup }}</div>
                    <x-slot name="closeTrigger">
                        <button wire:confirm="Are you sure?" wire:click="cancelSetup" wire:loading.attr="disabled"
                            wire:target="cancelSetup" class="text-primary-100">
                            <x-ri-close-fill class="size-6" />
                        </button>
                    </x-slot>
                </x-modal>
            @endif
        </x-modal>
    @endif

    <div class="wf-panel">
        <div class="wf-panel-heading wf-panel-heading--split">
            <span>{{ __('account.saved_payment_methods') }}</span>
            @if (count($this->gateways) > 0)
                <x-button.primary wire:click="$set('setupModalVisible', true)" wire:loading.attr="disabled"
                    wire:target="setupModalVisible">
                    {{ __('account.add_payment_method') }}
                </x-button.primary>
            @endif
        </div>
        <div class="wf-panel-body">
            <p class="wf-muted">{{ __('account.saved_payment_methods_description') }}</p>

            @forelse ($billingAgreements as $agreement)
                <div class="wf-row">
                    <div>
                        <strong>{{ $agreement->name }}</strong>
                        <div class="wf-muted">
                            {{ $agreement->gateway?->name }}
                            @if ($agreement->expiry)
                                — {{ __('account.expires', ['date' => \Carbon\Carbon::parse($agreement->expiry)->format('m/Y')]) }}
                            @endif
                        </div>
                    </div>
                    <x-button.danger x-on:click="$store.confirmation.confirm({
                        title: '{{ __('account.remove_payment_method') }}',
                        message: '{{ __('account.remove_payment_method_confirm', ['name' => $agreement->name]) }}',
                        confirmText: '{{ __('account.confirm') }}',
                        cancelText: '{{ __('account.cancel') }}',
                        callback: () => $wire.removePaymentMethod('{{ $agreement->ulid }}')
                    })">
                        {{ __('account.cancel') }}
                    </x-button.danger>
                </div>
            @empty
                <p class="wf-empty">{{ __('account.no_saved_payment_methods') }}</p>
            @endforelse
        </div>
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('account.recent_transactions') }}</div>
        <div class="wf-panel-body">
            @forelse ($transactions as $transaction)
                <a class="wf-row" href="{{ route('invoices.show', $transaction->invoice) }}" wire:navigate>
                    <div>
                        <strong>{{ $transaction->formattedAmount }}</strong>
                        <div class="wf-muted">
                            {{ $transaction->gateway?->name ?? 'N/A' }}
                            @if ($transaction->transaction_id)
                                — {{ $transaction->transaction_id }}
                            @endif
                        </div>
                    </div>
                    <div class="wf-muted">{{ $transaction->created_at->format('d M Y H:i') }}</div>
                </a>
            @empty
                <p class="wf-empty">{{ __('theme.no_transactions') }}</p>
            @endforelse

            {{ $transactions->links() }}
        </div>
    </div>
</div>
