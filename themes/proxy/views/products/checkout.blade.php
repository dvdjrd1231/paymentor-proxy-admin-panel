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

                    {{-- Shown even when there is only one plan, as on the reference, where a
                         single-cycle product still displays "Choose Billing Cycle" reading
                         "$70.00 USD Monthly". Hiding it left the customer with no statement
                         of what they were about to be billed. --}}
                    @if ($product->availablePlans()->count() > 0)
                        <x-form.select wire:model.live="plan_id" name="plan_id" label="{{ __('theme.choose_billing_cycle') }}">
                            @foreach ($product->availablePlans() as $availablePlan)
                                <option value="{{ $availablePlan->id }}">
                                    {{ $availablePlan->price()->formatted->price }} {{ $availablePlan->name }}
                                    @if ($availablePlan->price()->has_setup_fee)
                                        + {{ $availablePlan->price()->formatted->setup_fee }} {{ __('product.setup_fee') }}
                                    @endif
                                </option>
                            @endforeach
                        </x-form.select>
                    @endif

                    {{-- The reference divides the form with a centred "Configurable Options"
                         rule before the per-product choices. Rendered only when there is
                         something under it, so a product with no options gets no empty
                         heading. --}}
                    @if ($product->configOptions->isNotEmpty() || count($this->getCheckoutConfig()) > 0)
                        <div class="wf-legend">{{ __('theme.configurable_options') }}</div>
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

            {{-- The reference closes the configure column with a sales-help note. It links
                 Contact Us, which is the one informational page still reachable signed
                 out. --}}
            @if (Route::has('contact'))
                <div class="wf-help-note">
                    <span class="wf-help-ico">?</span>
                    {{ __('theme.sales_help') }}
                    <a href="{{ route('contact') }}" wire:navigate>{{ __('theme.click_here') }}</a>
                </div>
            @endif
        </div>

        {{-- ── Order summary ───────────────────────────────────────────── --}}
        <div>
            <div class="wf-summary wf-sticky">
                <div class="wf-summary-head">{{ __('product.order_summary') }}</div>
                <div class="wf-summary-body">
                    {{-- The reference itemises the order before the totals: what is being
                         bought, which category it came from, the line price, then one row
                         per configured option — so the figure at the bottom is traceable
                         rather than a bare number. --}}
                    <div class="wf-summary-item">
                        <span class="wf-summary-item-name">{{ $product->name }}</span>
                        @if ($product->category)
                            <span class="wf-summary-item-cat">{{ $product->category->name }}</span>
                        @endif
                    </div>

                    <div class="wf-total-row">
                        <span>{{ $product->name }}</span>
                        <span>{{ $total->format($total->price) }}</span>
                    </div>

                    @foreach ($product->configOptions as $configOption)
                        @php
                            $chosenId = $configOptions[$configOption->id] ?? null;
                            $chosen = $chosenId ? $configOption->children->firstWhere('id', $chosenId) : null;
                            $chosenPrice = $chosen?->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit);
                        @endphp
                        <div class="wf-total-row wf-total-row--opt">
                            <span>&raquo; {{ $configOption->name }}: {{ $chosen->name ?? __('theme.not_selected') }}</span>
                            <span>{{ $chosenPrice && $chosenPrice->available ? (string) $chosenPrice : $total->format(0) }}</span>
                        </div>
                    @endforeach

                    <div class="wf-total-row">
                        <span>{{ __('product.setup_fee') }}</span>
                        <span>{{ $total->format($total->setup_fee ?? 0) }}</span>
                    </div>

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
                            <span wire:loading.remove wire:target="checkout">{{ __('theme.continue') }} &rarr;</span>
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
