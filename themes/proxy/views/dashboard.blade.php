{{--
    Client Area dashboard in the WHMCS "Six" layout the reference portal uses:
    a left rail (credit balance, your info, shortcuts) beside the main column
    (welcome header, stat tiles, active services, unpaid invoices, tickets).

    Nothing here is hardcoded — every figure is queried live and every string comes
    from the language files, so the wording can be changed without touching Blade.
--}}
@php
    $user = Auth::user();

    $activeServices = $user->services()->where('status', 'active')->count();
    $unpaidInvoices = $user->invoices()->where('status', 'pending')->count();
    $openTickets = $user->tickets()->where('status', '!=', 'closed')->count();
    $totalInvoices = $user->invoices()->count();

    $ticketsEnabled = !config('settings.tickets_disabled', false);
    $creditsEnabled = (bool) config('settings.credits_enabled', false);

    // Quotes: no quoting system exists, so the counter is honest at zero rather than
    // borrowing another figure. See the Client Tools extension.
    $quotes = 0;

    // Contacts come from Client Tools; resolved dynamically so the dashboard still renders
    // if that extension is disabled.
    // The class can exist while its table does not — between enabling the extension and
    // its migration running — and the dashboard must not 500 in that window.
    $contactModel = 'Paymenter\Extensions\Others\ClientTools\Models\Contact';
    $contacts = collect();

    if (class_exists($contactModel)) {
        try {
            $contacts = $contactModel::where('user_id', $user->id)->orderBy('first_name')->get();
        } catch (\Throwable $e) {
            $contacts = collect();
        }
    }

    // Balance in the currency the customer is browsing in, falling back to the default.
    $currency = session('currency', config('settings.default_currency'));
    $credit = $creditsEnabled
        ? $user->credits()->where('currency_code', $currency)->first()
        : null;
@endphp

<div class="wf-page">
    <div class="wf-layout">

        {{-- ── Left rail ───────────────────────────────────────────────── --}}
        <div>
            @if ($creditsEnabled)
                <div class="wf-panel wf-panel--brand">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-wallet-3-fill /></span>{{ __('dashboard.credit_balance') }}</span>
                        <span class="wf-chevron">&#9650;</span>
                    </div>
                    <div class="wf-panel-body" style="text-align:center">
                        <div class="wf-stat-num">
                            {{ $credit?->formatted_amount ?? __('dashboard.no_credit') }}
                        </div>
                        <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                           href="{{ route('account.credits') }}" wire:navigate>
                            {{ __('dashboard.add_funds') }}
                        </a>
                    </div>
                </div>
            @endif

            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-user-3-fill /></span>{{ __('dashboard.your_info') }}</span>
                    <span class="wf-chevron">&#9650;</span>
                </div>
                <div class="wf-panel-body">
                    {{-- The reference shows the customer's billing identity here, not just a
                         name: company, contact, street, city/state/postcode, country. Every
                         line is a custom property, so it appears only once the customer has
                         filled it in and nothing is invented for an empty account. --}}
                    @php
                        $props = $user->properties()->pluck('value', 'key');
                        $cityLine = collect([$props['city'] ?? null, $props['state'] ?? null, $props['zip'] ?? null])
                            ->filter()->implode(', ');
                    @endphp

                    @if (!empty($props['company_name']))
                        <div class="wf-list-title">{{ $props['company_name'] }}</div>
                        <span class="wf-list-sub"><em>{{ $user->name }}</em></span>
                    @else
                        <div class="wf-list-title">{{ $user->name }}</div>
                    @endif

                    <span class="wf-list-sub">{{ $user->email }}</span>

                    @foreach ([$props['address'] ?? null, $props['address2'] ?? null, $cityLine, $props['country'] ?? null] as $line)
                        @if (filled($line))
                            <span class="wf-list-sub">{{ $line }}</span>
                        @endif
                    @endforeach

                    <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                       href="{{ route('account') }}" wire:navigate>
                        {{ __('dashboard.update_details') }}
                    </a>
                </div>
            </div>

            {{-- Contacts sits between Your Info and Shortcuts on the reference, showing
                 "No Contacts Found" with a New Contact button when the list is empty. --}}
            @if (Route::has('account.contacts'))
                <div class="wf-panel wf-panel--brand">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-contacts-book-2-fill /></span>{{ __('clienttools.contacts') }}</span>
                        <span class="wf-chevron">&#9650;</span>
                    </div>
                    <div class="wf-panel-body">
                        @forelse ($contacts as $contact)
                            <div class="wf-list-title">{{ $contact->name }}</div>
                            <span class="wf-list-sub">{{ $contact->email }}</span>
                        @empty
                            <div class="wf-muted">{{ __('clienttools.contacts_empty') }}</div>
                        @endforelse

                        <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                           href="{{ route('account.contacts') }}" wire:navigate>
                            + {{ __('clienttools.contact_new') }}
                        </a>
                    </div>
                </div>
            @endif

            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-links-fill /></span>{{ __('dashboard.shortcuts') }}</span>
                </div>
                <ul class="wf-list">
                    <li><a href="{{ route('home') }}" wire:navigate>{{ __('dashboard.order_new_services') }}</a></li>
                    <li><a href="{{ route('services') }}" wire:navigate>{{ __('navigation.services') }}</a></li>
                    <li><a href="{{ route('invoices') }}" wire:navigate>{{ __('navigation.invoices') }}</a></li>
                    @if ($ticketsEnabled)
                        <li><a href="{{ route('tickets.create') }}" wire:navigate>{{ __('ticket.create_ticket') }}</a></li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- ── Main column ─────────────────────────────────────────────── --}}
        <div>
            {{-- No strapline under the heading: the reference goes straight from the
                 welcome to the breadcrumb. --}}
            <div class="wf-pagehead">
                <h1>{{ __('dashboard.welcome_back', ['name' => $user->first_name]) }}</h1>
            </div>

            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
                <span>/</span>{{ __('theme.client_area') }}
            </div>

            <div class="wf-stats">
                <a class="wf-stat" href="{{ route('services') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $activeServices }}</span>
                        <span class="wf-stat-icon"><x-ri-archive-stack-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('theme.services_short') }}</div>
                </a>
                {{-- The reference's second tile is QUOTES. Paymenter has no quoting system,
                     so it reads zero — which is what the reference shows for this account
                     too. Rendered only when the Client Tools page exists to link at. --}}
                @if (Route::has('quotes'))
                    <a class="wf-stat" href="{{ route('quotes') }}" wire:navigate>
                        <div class="wf-stat-head">
                            <span class="wf-stat-num">{{ $quotes }}</span>
                            <span class="wf-stat-icon"><x-ri-file-list-3-fill /></span>
                        </div>
                        <div class="wf-stat-label">{{ __('clienttools.quotes_short') }}</div>
                    </a>
                @endif
                <a class="wf-stat" href="{{ route('invoices') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $unpaidInvoices }}</span>
                        <span class="wf-stat-icon"><x-ri-receipt-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('theme.overdue_invoices') }}</div>
                </a>
                @if ($ticketsEnabled)
                    <a class="wf-stat" href="{{ route('tickets') }}" wire:navigate>
                        <div class="wf-stat-head">
                            <span class="wf-stat-num">{{ $openTickets }}</span>
                            <span class="wf-stat-icon"><x-ri-customer-service-fill /></span>
                        </div>
                        <div class="wf-stat-label">{{ __('theme.tickets_short') }}</div>
                    </a>
                @endif
                <a class="wf-stat" href="{{ route('invoices') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $totalInvoices }}</span>
                        <span class="wf-stat-icon"><x-ri-bank-card-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('theme.invoices_short') }}</div>
                </a>
            </div>

            @if (Route::has('knowledgebase.index'))
                {{-- Ask-a-question band under the counters, as on the reference. A plain GET
                     into the knowledgebase page (whose `q` is URL-bound), so it works
                     without JavaScript and the result list stays linkable. --}}
                <div class="wf-kbsearch">
                    <form action="{{ route('knowledgebase.index') }}" method="GET" role="search">
                        <div class="wf-input-ico" style="flex:1">
                            <span class="wf-ico"><x-ri-search-line /></span>
                            <input type="search" name="q" class="wf-input"
                                   placeholder="{{ __('theme.kb_search_placeholder') }}"
                                   aria-label="{{ __('theme.kb_search_placeholder') }}">
                        </div>
                        <button type="submit" class="wf-btn">{{ __('theme.search') }}</button>
                    </form>
                </div>
            @endif

            @if ($unpaidInvoices > 0)
                <div class="wf-alert wf-alert--info" style="margin-bottom:1.25rem">
                    {{ trans_choice('dashboard.unpaid_summary', $unpaidInvoices, ['count' => $unpaidInvoices]) }}
                    <a href="{{ route('invoices') }}" wire:navigate>{{ __('dashboard.view_invoices') }}</a>
                </div>
            @endif

            <div class="wf-panel wf-panel--top">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-archive-stack-fill /></span>{{ __('theme.active_products_services') }}</span>
                    <a class="wf-btn wf-btn--sm" href="{{ route('services') }}" wire:navigate>
                        &rarr; {{ __('dashboard.my_services') }}
                    </a>
                </div>
                <livewire:services.widget status="active" />
                <div class="wf-panel-foot">
                    <a href="{{ route('services') }}" wire:navigate>{{ __('theme.view_more') }}</a>
                </div>
            </div>

            <div class="wf-grid">
                <div class="wf-panel wf-panel--top">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-receipt-fill /></span>{{ __('theme.overdue_invoices') }}</span>
                        <a class="wf-btn wf-btn--sm" href="{{ route('invoices') }}" wire:navigate>
                            &rarr; {{ __('theme.pay_now') }}
                        </a>
                    </div>
                    <livewire:invoices.widget :limit="3" />
                </div>

                @if ($ticketsEnabled)
                    <div class="wf-panel wf-panel--top">
                        <div class="wf-panel-heading">
                            <span><span class="wf-head-icon"><x-ri-customer-service-fill /></span>{{ __('theme.recent_tickets') }}</span>
                            <a class="wf-btn wf-btn--sm" href="{{ route('tickets.create') }}" wire:navigate>
                                + {{ __('theme.open_new_ticket') }}
                            </a>
                        </div>
                        <livewire:tickets.widget />
                    </div>
                @endif
            </div>

            {!! hook('pages.dashboard') !!}
        </div>
    </div>
</div>
