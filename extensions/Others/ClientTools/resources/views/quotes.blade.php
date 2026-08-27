{{-- My Quotes. Real quotes from `Others/Quotes` when it is installed; the reference portal's
     empty state when it is not, which is what this page showed before that existed. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.quotes') }}</h1>
        <span>{{ __('clienttools.quotes_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.quotes') }}
    </div>

    @forelse ($quotes as $quote)
        <div class="wf-panel">
            <div class="wf-panel-heading">
                <span>{{ $quote->subject }}</span>
                <span class="wf-label {{ match ($quote->status) {
                    'accepted' => 'wf-label--success',
                    'declined', 'expired' => 'wf-label--danger',
                    default => 'wf-label--info',
                } }}">{{ ucfirst($quote->status) }}</span>
            </div>

            <div class="wf-panel-body">
                <table class="wf-table">
                    <thead>
                        <tr>
                            <th>{{ __('clienttools.quotes_description') }}</th>
                            <th style="text-align:right">{{ __('clienttools.quotes_qty') }}</th>
                            <th style="text-align:right">{{ __('clienttools.quotes_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quote->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td style="text-align:right">
                                    {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}
                                </td>
                                <td style="text-align:right">
                                    {{ number_format($item->total(), 2) }} {{ $quote->currency_code }}
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="2" style="text-align:right"><strong>{{ __('clienttools.quotes_total') }}</strong></td>
                            <td style="text-align:right">
                                <strong>{{ number_format($quote->total(), 2) }} {{ $quote->currency_code }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>

                @if ($quote->notes)
                    <p style="white-space:pre-wrap; margin-top:.75rem">{{ $quote->notes }}</p>
                @endif

                @if ($quote->valid_until)
                    <p style="margin-top:.5rem">
                        {{ __('clienttools.quotes_valid_until') }}
                        <strong>{{ $quote->valid_until->format('j M Y') }}</strong>
                        {{-- Said, not enforced: a quote past its date stays answerable until the
                             daily sweep closes it, so a customer acting late on the closing day
                             is not turned away by a scheduler. --}}
                        @if ($quote->isLapsed())
                            — <span class="wf-label wf-label--warning">{{ __('clienttools.quotes_lapsed') }}</span>
                        @endif
                    </p>
                @endif

                @if ($quote->isOpen())
                    <div style="margin-top:1rem; display:flex; gap:.5rem">
                        {{-- `wire:confirm` because accepting creates a real invoice: it is an
                             agreement to pay, not a preference. --}}
                        <button type="button"
                            class="wf-btn wf-btn--primary"
                            wire:click="accept({{ $quote->id }})"
                            wire:confirm="{{ __('clienttools.quotes_accept_confirm') }}">
                            {{ __('clienttools.quotes_accept') }}
                        </button>

                        <button type="button"
                            class="wf-btn"
                            wire:click="decline({{ $quote->id }})"
                            wire:confirm="{{ __('clienttools.quotes_decline_confirm') }}">
                            {{ __('clienttools.quotes_decline') }}
                        </button>
                    </div>
                @elseif ($quote->invoice_id)
                    <p style="margin-top:1rem">
                        <a class="wf-link" href="{{ route('invoices.show', $quote->invoice_id) }}" wire:navigate>
                            {{ __('clienttools.quotes_see_invoice') }} #{{ $quote->invoice_id }}
                        </a>
                    </p>
                @endif
            </div>
        </div>
    @empty
        <div class="wf-alert wf-alert--info" style="text-align:center">
            {{ __('clienttools.quotes_empty') }}
        </div>
    @endforelse
</div>
