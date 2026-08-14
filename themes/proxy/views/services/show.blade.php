{{-- Service detail — WHMCS "Six" style. Product summary on the left, actions on the
     right, and the provisioning module's own fields (proxy credentials, addresses,
     status) rendered from $fields. All Livewire bindings match the default theme. --}}
<div class="wf-page">
    @if($invoice = $service->invoices()->where('status', 'pending')->first())
        <div class="wf-alert">
            {{ __('services.outstanding_invoice') }}
            <a href="{{ route('invoices.show', $invoice) }}">{{ __('services.view_and_pay') }}</a>.
        </div>
    @endif

    <div class="wf-pagehead">
        <h1>{{ $service->label ?? $service->product?->name }}</h1>
    </div>

    <div class="wf-grid">
        {{-- ── Details ─────────────────────────────────────────────────── --}}
        <div class="wf-panel">
            <div class="wf-panel-heading">{{ __('services.product_details') }}</div>
            <div class="wf-panel-body">
                <table class="wf-table wf-table--kv">
                    <tbody>
                        <tr>
                            <th>{{ __('services.price') }}</th>
                            <td>{{ $service->formattedPrice }}</td>
                        </tr>
                        @if($service->plan?->type == 'recurring')
                            <tr>
                                <th>{{ __('services.billing_cycle') }}</th>
                                <td>
                                    {{ __('services.every_period', [
                                        'period' => $service->plan->billing_period > 1 ? $service->plan->billing_period : '',
                                        'unit' => trans_choice(__('services.billing_cycles.' . $service->plan->billing_unit), $service->plan->billing_period),
                                    ]) }}
                                </td>
                            </tr>
                            @if($service->expires_at)
                                <tr>
                                    <th>{{ __('services.renews_on') }}</th>
                                    <td>{{ $service->expires_at->format('M d, Y') }}</td>
                                </tr>
                            @endif
                        @endif
                        <tr>
                            <th>{{ __('services.status') }}</th>
                            <td>
                                @if($service->cancellation && $service->status == 'active')
                                    <span class="wf-label wf-label--warning">{{ __('services.statuses.cancellation_pending') }}</span>
                                @else
                                    @php
                                        $tone = match ($service->status) {
                                            'active' => 'wf-label--success',
                                            'cancelled' => 'wf-label--danger',
                                            default => 'wf-label--warning',
                                        };
                                    @endphp
                                    <span class="wf-label {{ $tone }}">{{ __('services.statuses.' . $service->status) }}</span>
                                @endif
                            </td>
                        </tr>

                        {{-- Fields supplied by the provisioning module (ProxyPanel):
                             proxy username/password, addresses, host, panel status. --}}
                        @foreach ($fields as $field)
                            <tr>
                                <th>{{ $field['label'] }}</th>
                                <td class="wf-kv-value">{{ $field['text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @include('services.partials.billing-agreement')
            </div>
        </div>

        {{-- ── Actions ─────────────────────────────────────────────────── --}}
        @if($service->cancellable || $service->upgradable || count($buttons) > 0)
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('services.actions') }}</div>
                <div class="wf-panel-body">
                    <div class="wf-actions">
                        @if($service->upgradable)
                            <a class="wf-btn" href="{{ route('services.upgrade', $service->id) }}">{{ __('services.upgrade') }}</a>
                        @endif

                        @if($service->upgrade()->where('status', 'pending')->exists())
                            <button type="button" class="wf-btn wf-btn--ghost"
                                @click="Alpine.store('notifications').addNotification([{message: '{{ __('services.upgrade_pending') }}', type: 'error'}])">
                                {{ __('services.upgrade') }}
                            </button>
                        @endif

                        @foreach ($buttons as $button)
                            @if (isset($button['function']))
                                <button type="button" class="wf-btn wf-btn--ghost" wire:click="goto('{{ $button['function'] }}')">
                                    <span wire:loading.remove wire:target="goto('{{ $button['function'] }}')">{{ $button['label'] }}</span>
                                    <span wire:loading wire:target="goto('{{ $button['function'] }}')">…</span>
                                </button>
                            @else
                                <a class="wf-btn wf-btn--ghost" href="{{ $button['url'] }}"
                                    @if(!empty($button['target'])) target="{{ $button['target'] }}" @endif
                                    @if(($button['target'] ?? null) === '_blank') rel="noopener noreferrer" @endif>
                                    {{ $button['label'] }}
                                </a>
                            @endif
                        @endforeach

                        @if($service->cancellable)
                            <button type="button" class="wf-btn wf-btn--danger" wire:click="$set('showCancel', true)">
                                {{ __('services.cancel') }}
                            </button>
                        @endif
                    </div>

                    @if($showCancel)
                        <x-modal open="true"
                            title="{{ __('services.cancellation', ['service' => $service->product->name]) }}"
                            width="max-w-3xl">
                            <livewire:services.cancel :service="$service" />
                            <x-slot name="closeTrigger">
                                <button wire:click="$set('showCancel', false)" @click="open = false" class="text-primary-100">
                                    <x-ri-close-fill class="size-6" />
                                </button>
                            </x-slot>
                        </x-modal>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ── Module-provided views (tabs) ─────────────────────────────────── --}}
    @if (count($views) > 0)
        <div class="wf-panel">
            @if (count($views) > 1)
                <div class="wf-panel-heading wf-tabs">
                    @foreach ($views as $view)
                        <button type="button" wire:click="changeView('{{ $view['name'] }}')"
                            class="wf-tab {{ $view['name'] == $currentView ? 'wf-tab--active' : '' }}">
                            {{ $view['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
            <div class="wf-panel-body">
                <x-loading target="changeView" />
                <div wire:loading.remove wire:target="changeView">
                    {!! $extensionView !!}
                </div>
            </div>
        </div>
    @endif
</div>
