{{--
    The billing rail the reference portal shows beside Mass Payment and the invoice list:
    credit balance, what is outstanding, a count per status, and the Billing menu.

    Usage: <x-billing-rail active="mass-payment" />  — `active` marks the current page in
    the Billing panel and takes a route name.

    Every figure is queried from the signed-in customer's own invoices, so the rail never
    states a number the account does not have.
--}}
@props(['active' => null])

@php
    use App\Models\Invoice;

    $railUser = Auth::user();

    $railCounts = [
        'paid' => Invoice::where('user_id', $railUser->id)->where('status', Invoice::STATUS_PAID)->count(),
        'unpaid' => Invoice::where('user_id', $railUser->id)->where('status', Invoice::STATUS_PENDING)->count(),
        'cancelled' => Invoice::where('user_id', $railUser->id)->where('status', Invoice::STATUS_CANCELLED)->count(),
        // Not one of core's three constants, so it is counted from the column rather than a
        // constant: it reads zero unless something has actually been refunded.
        'refunded' => Invoice::where('user_id', $railUser->id)->where('status', 'refunded')->count(),
    ];

    $railUnpaid = Invoice::where('user_id', $railUser->id)
        ->where('status', Invoice::STATUS_PENDING)->get();

    $railDue = $railUnpaid->sum('remaining');
    $railCurrency = $railUnpaid->first()?->currency_code ?? config('settings.default_currency');

    $railCreditsOn = (bool) config('settings.credits_enabled', false);
    $railCredit = $railCreditsOn
        ? $railUser->credits()->where('currency_code', session('currency', config('settings.default_currency')))->first()
        : null;

    // Billing menu, in the reference's order. Each entry is dropped when the extension that
    // owns its page is not installed, rather than left as a dead row.
    $railMenu = array_values(array_filter([
        ['route' => 'invoices', 'label' => __('theme.my_invoices')],
        Route::has('quotes') ? ['route' => 'quotes', 'label' => __('clienttools.quotes')] : null,
        Route::has('mass-payment') ? ['route' => 'mass-payment', 'label' => __('clienttools.mass_payment')] : null,
        Route::has('account.credits') ? ['route' => 'account.credits', 'label' => __('dashboard.add_funds')] : null,
    ]));
@endphp

<div>
    @if ($railCreditsOn)
        <div class="wf-panel wf-panel--brand">
            <div class="wf-panel-heading">
                <span>{{ __('dashboard.credit_balance') }}</span>
                <span class="wf-chevron">&#9650;</span>
            </div>
            <div class="wf-panel-body" style="text-align:center">
                <div class="wf-stat-num">{{ $railCredit?->formatted_amount ?? __('dashboard.no_credit') }}</div>
                @if (Route::has('account.credits'))
                    <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                       href="{{ route('account.credits') }}" wire:navigate>+ {{ __('dashboard.add_funds') }}</a>
                @endif
            </div>
        </div>
    @endif

    @if ($railCounts['unpaid'] > 0)
        <div class="wf-panel wf-panel--brand">
            <div class="wf-panel-heading">
                <span><span class="wf-head-icon"><x-ri-bank-card-2-fill /></span>{{ trans_choice('theme.invoices_due', $railCounts['unpaid'], ['count' => $railCounts['unpaid']]) }}</span>
                <span class="wf-chevron">&#9650;</span>
            </div>
            <div class="wf-panel-body">
                <p>{{ trans_choice('theme.invoices_due_note', $railCounts['unpaid'], [
                    'count' => $railCounts['unpaid'],
                    'amount' => number_format($railDue, 2) . ' ' . $railCurrency,
                ]) }}</p>

                <div class="wf-actions" style="margin-top:.75rem">
                    @if (Route::has('mass-payment'))
                        <a class="wf-btn wf-btn--sm" href="{{ route('mass-payment') }}" wire:navigate>
                            {{ __('theme.pay_all') }}
                        </a>
                    @endif
                    @if (Route::has('account.credits'))
                        <a class="wf-btn wf-btn--sm" href="{{ route('account.credits') }}" wire:navigate>
                            {{ __('dashboard.add_funds') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Counts only. The reference filters its list from here; core's invoice page takes no
         status, so these would be controls that change nothing — the figures are real, the
         filtering is not claimed. --}}
    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-filter-3-fill /></span>{{ __('invoices.status') }}</span>
            <span class="wf-chevron">&#9650;</span>
        </div>
        <ul class="wf-list">
            @foreach (['paid', 'unpaid', 'cancelled', 'refunded'] as $railStatus)
                <li>
                    <span class="wf-list-row">
                        <span>{{ __('theme.status_' . $railStatus) }}</span>
                        <span class="wf-muted">{{ $railCounts[$railStatus] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-bank-fill /></span>{{ __('theme.billing') }}</span>
            <span class="wf-chevron">&#9650;</span>
        </div>
        <ul class="wf-list">
            @foreach ($railMenu as $railItem)
                <li>
                    <a href="{{ route($railItem['route']) }}" wire:navigate
                       class="{{ $active === $railItem['route'] ? 'is-active' : '' }}">
                        <span>{{ $railItem['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
