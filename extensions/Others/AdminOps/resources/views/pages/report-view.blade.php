{{--
    One report, to the reference screenshots: heading, description, the Tools menu, the
    multi-series line chart when the report has one, and the striped table.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-rv">
        <div class="ao-rv-head">
            <div>
                <h3>{{ $report['title'] }}</h3>
                <p>{{ $report['description'] }}</p>
                @isset($report['note'])
                    <p class="ao-rv-note"><b>{{ $report['note'] }}</b></p>
                @endisset
            </div>
            <details class="ao-mu-manage ao-rv-tools">
                <summary>&#9881; Tools <span aria-hidden="true">&#9662;</span></summary>
                <div class="ao-mu-manage-menu">
                    <button type="button" onclick="window.print()">Print</button>
                    <button type="button" wire:click="exportCsv">Export CSV</button>
                </div>
            </details>
        </div>

        @if ($report['chart'])
            @php
                $labels = $report['chart']['labels'];
                $series = $report['chart']['series'];
                $w = 900; $h = 260; $pad = 14; $left = 52;
                $peak = max(1, collect($series)->flatMap(fn ($s) => $s['points'])->max());
                $mag = 10 ** max(0, strlen((string) (int) $peak) - 1);
                $top = max(1, (int) (ceil($peak / $mag) * $mag));
                $step = ($w - $left - $pad) / max(1, count($labels) - 1);
                $y = fn ($v) => round($h - $pad - ($v / $top) * ($h - 2 * $pad), 1);
            @endphp
            <div class="ao-rv-chart">
                @isset($report['chart']['heading'])
                    <h4>{{ $report['chart']['heading'] }}</h4>
                @endisset
                <svg viewBox="0 0 {{ $w + 170 }} {{ $h + 60 }}" role="img" aria-label="{{ $report['title'] }} chart">
                    @foreach ([0, 0.25, 0.5, 0.75, 1] as $tick)
                        <line x1="{{ $left }}" y1="{{ $y($top * $tick) }}" x2="{{ $w - $pad }}" y2="{{ $y($top * $tick) }}"
                            stroke="#e5e5e5" stroke-width="1" />
                        <text x="{{ $left - 6 }}" y="{{ $y($top * $tick) + 3 }}" font-size="10" fill="#6b6b6b"
                            text-anchor="end">{{ number_format($top * $tick) }}</text>
                    @endforeach
                    @foreach ($series as $line)
                        <polyline fill="none" stroke="{{ $line['color'] }}" stroke-width="2"
                            points="{{ collect($line['points'])->map(fn ($v, $i) => round($left + $i * $step, 1) . ',' . $y($v))->implode(' ') }}" />
                    @endforeach
                    @foreach ($labels as $i => $label)
                        @if ($i % max(1, intdiv(count($labels), 15)) === 0)
                            <text x="{{ round($left + $i * $step, 1) }}" y="{{ $h + 16 }}" font-size="10" fill="#6b6b6b"
                                text-anchor="end" transform="rotate(-35 {{ round($left + $i * $step, 1) }} {{ $h + 16 }})">{{ $label }}</text>
                        @endif
                    @endforeach
                    @foreach ($series as $i => $line)
                        <rect x="{{ $w + 6 }}" y="{{ 20 + $i * 26 }}" width="22" height="6" rx="2" fill="{{ $line['color'] }}" />
                        <text x="{{ $w + 34 }}" y="{{ 27 + $i * 26 }}" font-size="12" fill="#2b2b2b">{{ $line['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        @endif

        <table class="ao-mu-grid ao-rv-table">
            <thead>
                <tr>
                    @foreach ($report['columns'] as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
