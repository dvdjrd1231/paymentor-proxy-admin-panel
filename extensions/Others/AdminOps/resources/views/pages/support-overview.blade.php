{{--
    Support Overview, to the reference screenshot: the Displaying band, the cream tile
    row, and the two charts — Average First Reply Time and Tickets Submitted by Hour.
    Everything is the last 30 days.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-so">
        <div class="ao-so-band">
            Displaying
            <select wire:change="$set('dept', $event.target.value)">
                <option value="">All Departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department }}" @selected($dept === $department)>{{ $department }}</option>
                @endforeach
            </select>
        </div>

        <div class="ao-so-tiles">
            @foreach ($tiles as $label => $value)
                <div class="ao-so-tile">
                    <span>{{ $label }}</span>
                    <b>{{ $value }}</b>
                </div>
            @endforeach
        </div>

        <div class="ao-so-charts">
            <div class="ao-so-chart">
                <h4>Average First Reply Time</h4>
                @if ($replyDays === [])
                    <p class="ao-so-none">No data</p>
                @else
                    @php
                        $w = 460; $h = 170; $pad = 10; $left = 40;
                        $days = array_keys($replyDays);
                        $peak = max(1, max($replyDays));
                        $step = ($w - $left - $pad) / max(1, count($days) - 1);
                        $points = collect(array_values($replyDays))->map(fn ($v, $i) => round($left + $i * $step, 1) . ',' . round($h - $pad - ($v / $peak) * ($h - 2 * $pad), 1))->implode(' ');
                    @endphp
                    <svg viewBox="0 0 {{ $w }} {{ $h + 20 }}" role="img" aria-label="Average first reply time per day, minutes">
                        <text x="{{ $left - 6 }}" y="{{ $pad + 4 }}" font-size="9" fill="#6b6b6b" text-anchor="end">{{ $peak }}m</text>
                        <text x="{{ $left - 6 }}" y="{{ $h - $pad }}" font-size="9" fill="#6b6b6b" text-anchor="end">0</text>
                        <polyline points="{{ $points }}" fill="none" stroke="#337ab7" stroke-width="2" />
                        @foreach ($days as $i => $day)
                            @if ($i % 2 === 0)
                                <text x="{{ round($left + $i * $step, 1) }}" y="{{ $h + 12 }}" font-size="9"
                                    fill="#6b6b6b" text-anchor="middle">{{ $day }}</text>
                            @endif
                        @endforeach
                    </svg>
                @endif
            </div>

            <div class="ao-so-chart">
                <h4>Tickets Submitted by Hour</h4>
                @php $w = 460; $h = 170; $pad = 10; $left = 30; $bw = ($w - $left - $pad) / 24; @endphp
                <svg viewBox="0 0 {{ $w }} {{ $h + 20 }}" role="img" aria-label="Tickets submitted by hour of day">
                    @foreach ($byHour as $hour => $count)
                        @php $bh = ($count / $hourMax) * ($h - 2 * $pad); @endphp
                        <rect x="{{ round($left + $hour * $bw + 1, 1) }}" y="{{ round($h - $pad - $bh, 1) }}"
                            width="{{ round($bw - 2, 1) }}" height="{{ round($bh, 1) }}" fill="#7ac143" />
                        @if ($hour % 4 === 0)
                            <text x="{{ round($left + $hour * $bw + $bw / 2, 1) }}" y="{{ $h + 12 }}" font-size="9"
                                fill="#6b6b6b" text-anchor="middle">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</text>
                        @endif
                    @endforeach
                    <text x="{{ $left - 5 }}" y="{{ $pad + 4 }}" font-size="9" fill="#6b6b6b" text-anchor="end">{{ $hourMax }}</text>
                    <text x="{{ ($w + $left) / 2 }}" y="{{ $h + 20 }}" font-size="9" fill="#6b6b6b" font-style="italic"
                        text-anchor="middle">Number of Tickets Submitted</text>
                </svg>
            </div>
        </div>
    </div>
</x-filament-panels::page>
