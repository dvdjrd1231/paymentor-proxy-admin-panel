{{-- Tickets widget as a Six-style list group (same $tickets data as the default theme). --}}
<ul class="wf-list">
    @forelse ($tickets as $ticket)
        @php
            $tone = match ($ticket->status) {
                'open' => 'wf-label--success',
                'closed' => '',
                'replied' => 'wf-label--info',
                default => 'wf-label--warning',
            };
        @endphp
        <li>
            <a href="{{ route('tickets.show', $ticket) }}" wire:navigate>
                <span style="min-width:0">
                    <span class="wf-list-title">#{{ $ticket->id }} &mdash; {{ $ticket->subject }}</span>
                    <span class="wf-list-sub">
                        {{ __('ticket.last_activity') }}
                        {{ $ticket->messages()->orderBy('created_at', 'desc')->first()?->created_at?->diffForHumans() }}
                        {{ $ticket->department ? ' · ' . $ticket->department : '' }}
                    </span>
                </span>
                <span class="wf-label {{ $tone }}">{{ ucfirst($ticket->status) }}</span>
            </a>
        </li>
    @empty
        <li><div class="wf-empty">{{ __('dashboard.open_tickets') }} &mdash; 0</div></li>
    @endforelse
</ul>
