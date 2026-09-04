{{--
    Automation Status — the reference's layout, taken from a screenshot of its own page.

    Three tiles across the top: overall verdict, when the automation last ran, when it next
    runs. Then **Daily Actions** — a tile per task carrying what it did today and, in red
    beside it, what failed. That failed count is the part worth copying: a task can
    half-work, and a grid that only showed successes would call that a good day.

    Two clocks feed the top row rather than one, because they fail differently and want
    different fixes. The heartbeat says whether `schedule:run` is in cron at all; the daily
    stamp says whether `app:cron-job` is completing. A fresh heartbeat with a stale daily run
    is a working scheduler and a failing job.
--}}
<x-filament-panels::page>
    {{-- The reference's three banner tiles: a darker icon square on the left, the big
         figure and its label on the right (issue #28). --}}
    <div class="ao-auto-head">
        <div class="ao-auto-head-tile {{ ($heartbeat['healthy'] && $daily['healthy']) ? 'ao-auto-ok' : 'ao-auto-bad' }}">
            <span class="ao-auto-head-ic">
                <x-filament::icon :icon="($heartbeat['healthy'] && $daily['healthy']) ? 'ri-check-line' : 'ri-close-line'" />
            </span>
            <span class="ao-auto-head-text">
                <span class="ao-auto-head-figure">
                    {{ ($heartbeat['healthy'] && $daily['healthy']) ? 'Ok' : 'Error' }}
                </span>
                <span class="ao-auto-head-label">
                    @if ($cronStatsUrl)
                        <a href="{{ $cronStatsUrl }}">View cron status</a>
                    @else
                        {{ ($heartbeat['healthy'] && $daily['healthy']) ? 'Automation is running' : 'See below to resolve' }}
                    @endif
                </span>
            </span>
        </div>

        <div class="ao-auto-head-tile ao-auto-neutral">
            <span class="ao-auto-head-ic"><x-filament::icon icon="ri-calendar-line" /></span>
            <span class="ao-auto-head-text">
                <span class="ao-auto-head-figure">
                    {{ $heartbeat['at']?->diffForHumans() ?? 'Never' }}
                </span>
                <span class="ao-auto-head-label">Last Cron Invocation</span>
            </span>
        </div>

        <div class="ao-auto-head-tile ao-auto-quiet">
            <span class="ao-auto-head-ic"><x-filament::icon icon="ri-calendar-check-line" /></span>
            <span class="ao-auto-head-text">
                <span class="ao-auto-head-figure">
                    {{ $nextRun->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                </span>
                {{-- The reference's own label carries no time suffix. --}}
                <span class="ao-auto-head-label" title="{{ $nextRun->format('H:i') }}">
                    Next Daily Task Run
                </span>
            </span>
        </div>
    </div>

    @if (! empty($problems))
        <div class="ao-auto-problems">
            @foreach ($problems as $problem)
                <div class="ao-auto-problem">
                    <strong>{{ $problem['title'] }}</strong>
                    <p>{{ $problem['body'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- No standing status line here — the reference has none when things are healthy,
         and problems() (above) already says so, more prominently, when they are not. --}}

    {{-- The reference's "Viewing X ▾ / This Week ▾" chart — a real trend line over each
         task's own CronStat rows, not a canned illustration. Only one range exists (the
         same seven days the tiles below already total), so it is a plain label rather than
         a dropdown offering a choice that does nothing. --}}
    @if (!empty($tasks))
        <div class="ao-auto-chart">
            {{-- Both controls sit at the right, as the reference draws them. --}}
            <div class="ao-auto-chart-head ao-auto-chart-head-right">
                <label class="ao-auto-chart-pick">
                    Viewing
                    <select wire:model.live="viewing">
                        @foreach ($tasks as $task)
                            <option value="{{ $task['key'] }}">{{ $task['title'] }}</option>
                        @endforeach
                    </select>
                </label>
                <span class="ao-auto-chart-range">This Week</span>
            </div>

            @php
                $max = max(1, ...array_column($chart['days'], 'value'));
                $w = 700;
                $h = 220;
                $stepX = $w / max(1, count($chart['days']) - 1);
                $points = collect($chart['days'])->values()->map(fn ($day, $i) => [
                    'x' => round($i * $stepX, 1),
                    'y' => round($h - ($day['value'] / $max) * ($h - 20), 1),
                ]);
                $line = $points->map(fn ($p) => "{$p['x']},{$p['y']}")->implode(' ');
                $area = "0,{$h} {$line} {$w},{$h}";
            @endphp
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="ao-auto-chart-svg" preserveAspectRatio="none">
                <polygon points="{{ $area }}" class="ao-auto-chart-fill" />
                <polyline points="{{ $line }}" class="ao-auto-chart-line" />
                @foreach ($points as $p)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" class="ao-auto-chart-dot" />
                @endforeach
            </svg>
            <div class="ao-auto-chart-axis">
                @foreach ($chart['days'] as $day)
                    <span title="{{ $day['value'] }} {{ $chart['did'] }}">{{ $day['label'] }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ao-auto-daily-head">
        <h3 class="ao-auto-section">Daily Actions</h3>
        <span class="ao-auto-today-label">Today</span>
    </div>

    <div class="ao-auto-daily">
        <div class="ao-auto-daily-tiles">
            @if (empty($tasks))
                <p class="ao-catalogue-count">
                    No task has recorded anything in the last seven days, which on a working install
                    only happens on a brand new one.
                </p>
            @else
                <div class="ao-auto-tiles">
                    @foreach ($tasks as $task)
                        <div class="ao-auto-tile">
                            {{-- The reference's grey glyph in the tile's top right corner. --}}
                            <div class="ao-auto-tile-head">
                                {{ $task['title'] }}
                                <x-filament::icon :icon="$task['icon']" class="ao-auto-tile-ic" />
                            </div>
                            @if (!empty($task['disabled']))
                                {{-- The reference's own disabled-tile treatment: a dash
                                     and a grey Disabled, the reason on the hover. --}}
                                <div class="ao-auto-tile-body" title="{{ $task['disabled'] }}">
                                    <span class="ao-auto-tile-figure ao-auto-tile-dash">-</span>
                                </div>
                                <div class="ao-auto-tile-foot">
                                    <span></span>
                                    <span class="ao-auto-tile-off" title="{{ $task['disabled'] }}">Disabled</span>
                                </div>
                            @else
                                <div class="ao-auto-tile-body">
                                    <span class="ao-auto-tile-figure">{{ number_format($task['today']) }}</span>
                                    <span class="ao-auto-tile-did">{{ $task['did'] }}</span>
                                </div>
                                {{-- Week total left, the reference's red failed count right. --}}
                                <div class="ao-auto-tile-foot">
                                    <span>{{ number_format($task['week']) }} in the last 7 days</span>
                                    @if ($task['failed'] > 0)
                                        <span class="ao-auto-tile-failed">{{ number_format($task['failed']) }} Failed</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- The reference's second tier under Daily Actions: a status line each,
                 not a count — Database Backup, Platform Updates, Currency Exchange
                 Rates. See AutomationStatus::systemTasks() for what each really checks. --}}
            @if (!empty($systemTasks))
                <div class="ao-auto-tiles ao-auto-tiles-system">
                    @foreach ($systemTasks as $task)
                        <div class="ao-auto-tile ao-auto-tile-system">
                            <div class="ao-auto-tile-head">
                                {{ $task['label'] }}
                                <x-filament::icon :icon="$task['icon']" class="ao-auto-tile-ic" />
                            </div>
                            <div class="ao-auto-tile-body ao-auto-tile-body-system">
                                <x-filament::icon
                                    :icon="$task['ok'] ? 'ri-checkbox-circle-fill' : 'ri-close-circle-fill'"
                                    class="{{ $task['ok'] ? 'ao-auto-sys-ok' : 'ao-auto-sys-off' }}" />
                                <span>{{ $task['note'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- The reference's month calendar beside the tiles; each day opens the real
             Calendar page, today carries its amber highlight. --}}
        <aside class="ao-auto-cal">
            <div class="ao-auto-cal-month">{{ $calendar['month'] }}</div>
            <table class="ao-auto-cal-grid">
                <thead>
                    <tr>@foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $dow)<th>{{ $dow }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($calendar['weeks'] as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td class="{{ $day['other'] ? 'ao-auto-cal-other' : '' }} {{ $day['today'] ? 'ao-auto-cal-today' : '' }}">
                                    @if ($calendar['url'])
                                        <a href="{{ $calendar['url'] }}">{{ $day['n'] }}</a>
                                    @else
                                        {{ $day['n'] }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($calendar['url'])
                <a class="ao-auto-cal-today-btn" href="{{ $calendar['url'] }}">Today</a>
            @endif
        </aside>
    </div>

    {{--
        Said plainly because the figures above are honest but misleading on their own: the
        daily job runs once, at a configured hour, so every "today" is legitimately zero
        until it has run. The seven-day line on each tile is what shows a task has stopped.
    --}}
    <p class="ao-catalogue-count">
        The daily job runs once a day at {{ config('settings.cronjob_time', '00:00') }}, so today's
        figures stay at zero until it has run — the seven-day line under each tile is the one that
        shows whether a task has actually stopped. Fixed-term terminations and cancellations do not
        wait for it; they run every minute and every hour respectively.
    </p>
</x-filament-panels::page>
