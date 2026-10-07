{{-- Manage Product — the reference portal's service page (Leandro, 2026-10-06).

     Rail on the left: credit balance, Overview, and every action the provisioning module
     offers as its own row. Main column: the product as a card with its status under it,
     the billing facts beside it, and the module's fields under a Configurable Options
     heading.

     All Livewire bindings are unchanged — goto() for a module button, changeView() for a
     module view, $set('showCancel') and the cancel modal. --}}
@php
    $creditsEnabled = (bool) config('settings.credits_enabled', false);
    $currency = session('currency', config('settings.default_currency'));
    $credit = $creditsEnabled ? Auth::user()->credits()->where('currency_code', $currency)->first() : null;

    $statusTone = match ($service->status) {
        'active' => 'wf-prod-status--active',
        'cancelled' => 'wf-prod-status--danger',
        default => 'wf-prod-status--warning',
    };

    // The gateway the service was actually paid through, which is what the reference shows
    // as Payment Method. Nothing is invented when it has never been paid.
    $paymentMethod = null;

    try {
        $paymentMethod = $service->invoices->flatMap->transactions->first()?->gateway?->name;
    } catch (\Throwable $e) {
        $paymentMethod = null;
    }
@endphp

<div class="wf-page">
    @if($invoice = $service->invoices()->where('status', 'pending')->first())
        <div class="wf-alert">
            {{ __('services.outstanding_invoice') }}
            <a href="{{ route('invoices.show', $invoice) }}">{{ __('services.view_and_pay') }}</a>.
        </div>
    @endif

    {{-- Which pane the main column shows. The reference swaps the content out when an
         action is chosen — the card and its tabs give way to that page — rather than
         stacking it underneath (Leandro, 2026-10-06). Client-side, because both panes
         are already on the page; only the module pane's innards come from the server,
         and changeView() replaces those without touching this element. --}}
    {{-- What the last action said. The rail's buttons can fail on the panel's side, and
         without this the page simply re-rendered as though nothing had happened. --}}
    @if (session('success'))
        <div class="wf-alert wf-alert--success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="wf-alert wf-alert--danger">{{ session('error') }}</div>
    @endif

    <div class="wf-layout" x-data="{ pane: 'info' }">
        {{-- ── Rail ────────────────────────────────────────────────────── --}}
        <div>
            @if ($creditsEnabled)
                <div class="wf-panel wf-panel--brand">
                    <div class="wf-panel-heading">
                        <span>{{ __('dashboard.credit_balance') }}</span>
                        <span class="wf-chevron">&#9650;</span>
                    </div>
                    <div class="wf-panel-body" style="text-align:center">
                        <div class="wf-stat-num">{{ $credit?->formatted_amount ?? __('dashboard.no_credit') }}</div>
                        <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                           href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a>
                    </div>
                </div>
            @endif

            {{-- Overview, with the page you are on marked — the reference's own rail. --}}
            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-star-fill /></span>{{ __('theme.overview') }}</span>
                    <span class="wf-chevron">&#9650;</span>
                </div>
                <ul class="wf-list">
                    <li>
                        <button type="button" class="wf-rowbtn"
                            :class="{ 'is-active': pane === 'info' }" @click="pane = 'info'">
                            {{ __('theme.information') }}
                        </button>
                    </li>
                </ul>
            </div>

            {{-- Every module action as a row of its own, rather than a strip of buttons in
                 the main column. --}}
            @if($service->cancellable || $service->upgradable || count($buttons) > 0)
                <div class="wf-panel wf-panel--brand">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-tools-fill /></span>{{ __('services.actions') }}</span>
                        <span class="wf-chevron">&#9650;</span>
                    </div>
                    <ul class="wf-list">
                        @if($service->upgradable)
                            <li>
                                <a href="{{ route('services.upgrade', $service->id) }}">
                                    <span>{{ __('services.upgrade') }}</span>
                                </a>
                            </li>
                        @endif

                        @if($service->upgrade()->where('status', 'pending')->exists())
                            <li class="wf-rowaction">
                                <button type="button"
                                    @click="Alpine.store('notifications').addNotification([{message: '{{ __('services.upgrade_pending') }}', type: 'error'}])">
                                    {{ __('services.upgrade') }}
                                </button>
                            </li>
                        @endif

                        {{-- Each module page as its own row, the one being shown marked —
                             the reference's sidebar, rather than a strip of tabs over the
                             content. --}}
                        @foreach ($views as $view)
                            <li class="wf-rowaction"
                                :class="{ 'is-active': pane === 'module' && @js($view['name']) === @js($currentView) }">
                                <button type="button" wire:click="changeView('{{ $view['name'] }}')"
                                    @click="pane = 'module'">
                                    <span wire:loading.remove wire:target="changeView('{{ $view['name'] }}')">{{ $view['label'] }}</span>
                                    <span wire:loading wire:target="changeView('{{ $view['name'] }}')">…</span>
                                </button>
                            </li>
                        @endforeach

                        @foreach ($buttons as $button)
                            @if (isset($button['function']))
                                <li class="wf-rowaction">
                                    <button type="button" wire:click="goto('{{ $button['function'] }}')">
                                        <span wire:loading.remove wire:target="goto('{{ $button['function'] }}')">{{ $button['label'] }}</span>
                                        <span wire:loading wire:target="goto('{{ $button['function'] }}')">…</span>
                                    </button>
                                </li>
                            @else
                                <li>
                                    <a href="{{ $button['url'] }}"
                                        @if(!empty($button['target'])) target="{{ $button['target'] }}" @endif
                                        @if(($button['target'] ?? null) === '_blank') rel="noopener noreferrer" @endif>
                                        <span>{{ $button['label'] }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach

                        @if($service->cancellable)
                            <li class="wf-rowaction">
                                <button type="button" wire:click="$set('showCancel', true)">
                                    {{ __('services.request_cancellation') }}
                                </button>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>

        {{-- ── Main ────────────────────────────────────────────────────── --}}
        <div>
            <div class="wf-title">
                <h1>{{ __('theme.manage_product') }}</h1>
            </div>
            <hr class="wf-title-rule">

            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
                <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
                <span>/</span><a href="{{ route('services') }}" wire:navigate>{{ __('theme.my_products_services') }}</a>
                <span>/</span>{{ __('services.product_details') }}
            </div>

            {{-- The card, the facts and the tabs are one pane; an action page is the
                 other. The title and breadcrumb above stay put, as they do on the
                 reference. --}}
            <div x-show="pane === 'info'">
            <div class="wf-grid">
                {{-- The product as the reference shows it: an icon over its name, the group
                     it came from, and the status as a bar across the foot of the card. --}}
                <div class="wf-prodcard">
                    <div class="wf-prodcard-body">
                        <span class="wf-prodcard-icon"><x-ri-archive-2-fill /></span>
                        <div class="wf-prodcard-name">{{ $service->product?->name ?? $service->label }}</div>
                        @if ($service->product?->category)
                            <div class="wf-prodcard-cat">{{ $service->product->category->name }}</div>
                        @endif
                    </div>
                    <div class="wf-prod-status {{ $statusTone }}">
                        @if($service->cancellation && $service->status == 'active')
                            {{ __('services.statuses.cancellation_pending') }}
                        @else
                            {{ __('services.statuses.' . $service->status) }}
                        @endif
                    </div>
                </div>

                {{-- Billing facts, each a label over its value, centred as the reference
                     centres them. A dash where there is nothing to state. --}}
                <div class="wf-facts">
                    <div class="wf-fact">
                        <span class="wf-fact-label">{{ __('theme.registration_date') }}</span>
                        <span class="wf-fact-value">{{ $service->created_at?->format('l, F jS, Y') ?: '-' }}</span>
                    </div>
                    <div class="wf-fact">
                        <span class="wf-fact-label">{{ __('theme.first_payment_amount') }}</span>
                        <span class="wf-fact-value">{{ $service->formattedPrice }}</span>
                    </div>
                    <div class="wf-fact">
                        <span class="wf-fact-label">{{ __('theme.billing_cycle') }}</span>
                        <span class="wf-fact-value"><x-cycle :plan="$service->plan" /></span>
                    </div>
                    <div class="wf-fact">
                        <span class="wf-fact-label">{{ __('theme.next_due_date') }}</span>
                        <span class="wf-fact-value">{{ $service->expires_at?->format('M d, Y') ?: '-' }}</span>
                    </div>
                    <div class="wf-fact">
                        <span class="wf-fact-label">{{ __('theme.payment_method') }}</span>
                        <span class="wf-fact-value">{{ $paymentMethod ?: '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- The module's own fields, under the heading the reference gives them. --}}
            {{-- Two tabs over one panel, as the reference has them. The configured options
                 the customer chose at checkout come first — "Region: United States - Kansas
                 City" — then the module's own fields, which is the order the reference lists
                 them in. Switching is client-side: both panes are already on the page. --}}
            <div x-data="{ tab: 'config' }" style="margin-top:1.25rem">
                <div class="wf-navtabs" role="tablist">
                    <button type="button" class="wf-navtab" role="tab"
                        :class="{ 'wf-navtab--active': tab === 'config' }" @click="tab = 'config'">
                        <x-ri-settings-3-fill />{{ __('theme.configurable_options') }}
                    </button>
                    <button type="button" class="wf-navtab" role="tab"
                        :class="{ 'wf-navtab--active': tab === 'info' }" @click="tab = 'info'">
                        <x-ri-information-fill />{{ __('theme.additional_information') }}
                    </button>
                </div>

                <div class="wf-tabpanel" x-show="tab === 'config'" role="tabpanel">
                    <table class="wf-table wf-table--kv wf-table--conf">
                        <tbody>
                            @foreach ($service->configs as $config)
                                <tr>
                                    <th>{{ $config->configOption?->name }}</th>
                                    <td class="wf-kv-value">{{ $config->configValue?->name ?? $config->value }}</td>
                                </tr>
                            @endforeach
                            @foreach ($fields as $field)
                                <tr>
                                    <th>{{ $field['label'] }}</th>
                                    <td class="wf-kv-value">{{ $field['text'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="wf-tabpanel" x-show="tab === 'info'" x-cloak role="tabpanel">
                    @if (filled($service->product?->description))
                        <article class="prose dark:prose-invert prose-sm">{!! $service->product->description !!}</article>
                    @else
                        <div class="wf-empty">{{ __('services.no_additional_information') }}</div>
                    @endif
                </div>
            </div>
            </div>{{-- /info pane --}}

            <div x-show="pane === 'module'" x-cloak>
                <x-loading target="changeView" />
                <div wire:loading.remove wire:target="changeView">
                    {!! $extensionView !!}
                </div>
            </div>

            @include('services.partials.billing-agreement')

            {{-- ── Module-provided views (tabs) ─────────────────────────── --}}
            @if (count($views) > 0)

            @endif

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
</div>
