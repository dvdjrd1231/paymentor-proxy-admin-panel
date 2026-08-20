{{-- Order form — WHMCS "Six" style: configure on the left, sticky order summary on the
     right. All Livewire bindings (plan_id, configOptions, checkoutConfig, checkout) and
     the shared x-form.* components are unchanged from the default theme. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('theme.configure') }}</h1>
        <p>{{ __('theme.configure_intro') }}</p>
    </div>

    <div class="wf-layout">
        <x-store-rail :active="$product->category" />

        <div class="wf-layout wf-layout--reverse">
        {{-- ── Configuration ───────────────────────────────────────────── --}}
        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('theme.configure') }} &mdash; {{ $product->name }}</div>
                <div class="wf-panel-body">
                    @if ($product->image || $product->description)
                        <div class="wf-product-intro">
                            @if ($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
                            @endif
                            <article class="prose dark:prose-invert prose-sm">{!! $product->description !!}</article>
                        </div>
                    @endif

                    @if ($product->availablePlans()->count() > 1)
                        <x-form.select wire:model.live="plan_id" name="plan_id" label="{{ __('theme.select_plan') }}">
                            @foreach ($product->availablePlans() as $availablePlan)
                                <option value="{{ $availablePlan->id }}">
                                    {{ $availablePlan->name }} -
                                    {{ $availablePlan->price()->formatted->price }}
                                    @if ($availablePlan->price()->has_setup_fee)
                                        + {{ $availablePlan->price()->formatted->setup_fee }} {{ __('product.setup_fee') }}
                                    @endif
                                </option>
                            @endforeach
                        </x-form.select>
                    @endif

                    @foreach ($product->configOptions as $configOption)
                        @php
                            $showPriceTag = $configOption->children->filter(fn ($value) => !$value->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->is_free)->count() > 0;
                        @endphp
                        <x-form.configoption :config="$configOption" :name="'configOptions.' . $configOption->id" :showPriceTag="$showPriceTag" :plan="$plan">
                            @if ($configOption->type == 'select')
                                @foreach ($configOption->children as $configOptionValue)
                                    <option value="{{ $configOptionValue->id }}">
                                        {{ $configOptionValue->name }}
                                        {{ ($showPriceTag && $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? ' - ' . $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : '' }}
                                    </option>
                                @endforeach
                            @elseif($configOption->type == 'radio')
                                @foreach ($configOption->children as $configOptionValue)
                                    <div class="wf-check">
                                        <input type="radio" id="{{ $configOptionValue->id }}" name="{{ $configOption->id }}"
                                            wire:model.live="configOptions.{{ $configOption->id }}"
                                            value="{{ $configOptionValue->id }}" />
                                        <label for="{{ $configOptionValue->id }}">
                                            {{ $configOptionValue->name }}
                                            {{ ($showPriceTag && $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? ' - ' . $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : '' }}
                                        </label>
                                    </div>
                                @endforeach
                            @endif
                        </x-form.configoption>
                    @endforeach

                    @foreach ($this->getCheckoutConfig() as $configOption)
                        @php $configOption = (object) $configOption; @endphp
                        <x-form.configoption :config="$configOption" :name="'checkoutConfig.' . $configOption->name">
                            @if ($configOption->type == 'select')
                                @foreach ($configOption->options as $configOptionValue => $configOptionValueName)
                                    <option value="{{ $configOptionValue }}"
                                        @disabled(in_array((string) $configOptionValue, array_map('strval', $configOption->disabled_options ?? []), true))>
                                        {{ $configOptionValueName }}
                                    </option>
                                @endforeach
                            @elseif($configOption->type == 'radio')
                                @foreach ($configOption->options as $configOptionValue => $configOptionValueName)
                                    <div class="wf-check">
                                        <input type="radio" id="{{ $configOptionValue }}" name="{{ $configOption->name }}"
                                            wire:model.live="checkoutConfig.{{ $configOption->name }}"
                                            value="{{ $configOptionValue }}" />
                                        <label for="{{ $configOptionValue }}">{{ $configOptionValueName }}</label>
                                    </div>
                                @endforeach
                            @endif
                        </x-form.configoption>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Order summary ───────────────────────────────────────────── --}}
        <div>
            <div class="wf-summary wf-sticky">
                <div class="wf-summary-head">{{ __('product.order_summary') }}</div>
                <div class="wf-summary-body">
                    @if ($total->total_tax > 0)
                        <div class="wf-total-row">
                            <span>{{ __('invoices.subtotal') }}</span>
                            <span>{{ $total->format($total->subtotal) }}</span>
                        </div>
                        <div class="wf-total-row">
                            <span>{{ \App\Classes\Settings::tax()->name }} ({{ \App\Classes\Settings::tax()->rate }}%)</span>
                            <span>{{ $total->formatted->total_tax }}</span>
                        </div>
                    @endif

                    <div class="wf-summary-total">
                        <strong>{{ $total }}</strong>
                        <span>{{ __('theme.total_due_today') }}</span>
                    </div>

                    @if ($total->setup_fee > 0 && $plan->type == 'recurring')
                        <div class="wf-total-row">
                            <span>{{ __('product.then_after_x', ['time' => $plan->billing_period . ' ' . trans_choice(__('services.billing_cycles.' . $plan->billing_unit), $plan->billing_period)]) }}</span>
                            <span>{{ $total->format($total->price) }}</span>
                        </div>
                    @endif

                    @if (($product->stock > 0 || !$product->stock) && $product->price()->available)
                        <button type="button" class="wf-btn wf-btn--checkout" style="margin-top:.9rem"
                            wire:click="checkout" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="checkout">{{ __('product.checkout') }}</span>
                            <span wire:loading wire:target="checkout">…</span>
                        </button>
                    @else
                        <p class="wf-section-note" style="margin-top:.75rem">{{ __('product.out_of_stock') ?? 'Currently unavailable.' }}</p>
                    @endif
                </div>
                <div class="wf-summary-foot"><a href="{{ route('home') }}" wire:navigate>{{ __('theme.continue_shopping') }}</a></div>
            </div>
        </div>
        </div>
    </div>
</div>
