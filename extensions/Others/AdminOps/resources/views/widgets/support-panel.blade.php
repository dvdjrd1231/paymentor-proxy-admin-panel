<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Support</x-slot>
        <div class="ao-wg-cols">
            <div class="ao-wg-icostat">
                <span class="ao-wg-ic" style="color: #5bc0de">&#127991;</span>
                <span>Awaiting Reply<br><b class="ao-wg-blue">{{ number_format($awaiting) }}</b> Tickets</span>
            </div>
            <div class="ao-wg-icostat">
                <span class="ao-wg-ic" style="color: #d9534f">&#9873;</span>
                <span>Assigned To You<br><b class="ao-wg-pink">{{ number_format($mine) }}</b> Tickets</span>
            </div>
        </div>

        <ul class="ao-wg-tickets">
            @forelse ($recent as $ticket)
                <li>
                    <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::getUrl(['record' => $ticket->id]) }}">
                        #{{ $ticket->id }} - {{ str($ticket->subject)->limit(38) }}
                    </a>
                    <i>{{ $ticket->updated_at?->diffForHumans(short: true) }}</i>
                </li>
            @empty
                <li class="ao-wg-empty">No tickets</li>
            @endforelse
        </ul>

        @if ($urls['all'])
            <p class="ao-wg-links">
                <a href="{{ $urls['all'] }}">View All Tickets</a>
                <a href="{{ $urls['mine'] }}">View My Tickets</a>
                <a href="{{ $urls['open'] }}">Open New Ticket</a>
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
