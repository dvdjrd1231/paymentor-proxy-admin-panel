{{-- Tickets list — WHMCS "Six" style table in a panel. Same $tickets paginator. --}}
<div class="wf-page">
    <div class="wf-pagehead" style="display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap">
        <h1 style="margin:0">{{ __('navigation.tickets') }}</h1>
        <a href="{{ route('tickets.create') }}" class="wf-btn wf-btn--sm" wire:navigate>+ {{ __('ticket.create_ticket') }}</a>
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('navigation.tickets') }}</div>
        <div class="wf-table-wrap">
            <table class="wf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('ticket.subject') }}</th>
                        <th>{{ __('ticket.last_activity') }}</th>
                        <th style="text-align:end">{{ __('ticket.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        @php
                            $tone = match ($ticket->status) {
                                'open' => 'wf-label--success',
                                'closed' => '',
                                'replied' => 'wf-label--info',
                                default => 'wf-label--warning',
                            };
                        @endphp
                        <tr>
                            <td>#{{ $ticket->id }}</td>
                            <td>
                                <a href="{{ route('tickets.show', $ticket) }}" wire:navigate>{{ $ticket->subject }}</a>
                                @if ($ticket->department)
                                    <span class="wf-list-sub">{{ $ticket->department }}</span>
                                @endif
                            </td>
                            <td>{{ $ticket->messages()->orderBy('created_at', 'desc')->first()?->created_at?->diffForHumans() }}</td>
                            <td style="text-align:end"><span class="wf-label {{ $tone }}">{{ ucfirst($ticket->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="wf-empty">{{ __('ticket.no_tickets') }}</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $tickets->links() }}
</div>
