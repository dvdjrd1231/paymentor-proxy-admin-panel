{{-- Calendar: the month grid, with services falling due and unpaid invoices on their days. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-cal">
        <div class="ao-mu-line">
            <button type="button" class="ao-cq-addline" wire:click="move(-1)">&laquo; {{ $month->copy()->subMonth()->format('F') }}</button>
            <b class="ao-cal-title">{{ $month->format('F Y') }}</b>
            <button type="button" class="ao-cq-addline" wire:click="move(1)">{{ $month->copy()->addMonth()->format('F') }} &raquo;</button>
        </div>

        <div class="ao-cal-grid">
            @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day)
                <div class="ao-cal-head">{{ $day }}</div>
            @endforeach

            @for ($i = 0; $i < $lead; $i++)
                <div class="ao-cal-cell ao-cal-blank"></div>
            @endfor

            @for ($day = 1; $day <= $days; $day++)
                <div class="ao-cal-cell {{ $month->copy()->day($day)->isToday() ? 'ao-cal-today' : '' }}">
                    <b>{{ $day }}</b>
                    @foreach ($events[$day] ?? [] as $event)
                        <span class="ao-cal-event ao-cal-{{ $event['kind'] }}">{{ $event['label'] }}</span>
                    @endforeach
                </div>
            @endfor
        </div>

        <p class="ao-cal-legend">
            <span class="ao-cal-event ao-cal-service">Service due</span>
            <span class="ao-cal-event ao-cal-invoice">Invoice due</span>
        </p>
    </div>
</x-filament-panels::page>
