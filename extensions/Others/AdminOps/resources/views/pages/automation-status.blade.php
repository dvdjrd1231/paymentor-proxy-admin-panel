{{--
    Automation Status — the reference's health-first layout.

    Two clocks at the top because they fail differently and need different fixes: the
    heartbeat says whether `schedule:run` is in cron at all, the daily stamp says whether
    `app:cron-job` is completing. A fresh heartbeat with a stale daily run is a working
    scheduler and a failing job, which is a different problem than a dead cron.

    Problems are stated above the figures, not below them. The figures for a dead scheduler
    are a tidy row of zeroes, and zero is exactly what "nothing to do" looks like.
--}}
<x-filament-panels::page>
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

    <div class="ao-auto-clocks">
        @foreach ([
            ['Scheduler', $heartbeat, 'Stamped every minute. This is what says cron is running at all.'],
            ['Daily job', $daily, 'app:cron-job — renewal invoices, suspensions, terminations.'],
        ] as [$label, $clock, $note])
            <div class="ao-auto-clock {{ $clock['healthy'] ? 'ao-auto-ok' : 'ao-auto-bad' }}">
                <div class="ao-auto-clock-head">
                    <span class="ao-auto-dot"></span>
                    <span class="ao-auto-label">{{ $label }}</span>
                    <span class="ao-auto-verdict">{{ $clock['healthy'] ? 'Running' : 'Not running' }}</span>
                </div>

                <div class="ao-auto-when">
                    @if ($clock['at'])
                        Last seen {{ $clock['at']->diffForHumans() }}
                        <span class="ao-auto-exact">· {{ $clock['at']->toDayDateTimeString() }}</span>
                    @else
                        Never seen
                    @endif
                </div>

                <p class="ao-auto-note">{{ $note }} Considered stale after {{ $clock['threshold'] }}.</p>
            </div>
        @endforeach
    </div>

    <div class="ao-panel">
        <table class="ao-cat-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th class="ao-col-stock">Today</th>
                    <th class="ao-col-stock">Last 7 days</th>
                    <th>Last recorded</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr>
                        <td>{{ $task['label'] }}</td>
                        <td class="ao-col-stock">{{ number_format($task['today']) }}</td>
                        <td class="ao-col-stock">{{ number_format($task['week']) }}</td>
                        <td>{{ $task['lastSeen'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            No task has recorded anything in the last seven days — which on a working
                            install only happens on a brand new one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{--
        Said plainly because the number above is honest but misleading on its own: the daily
        job runs once, at a configured hour, so every figure in the Today column is legitimately
        zero until it has run.
    --}}
    <p class="ao-catalogue-count">
        The daily job runs once a day at {{ config('settings.cronjob_time', '00:00') }}, so today's
        figures stay at zero until it has run. The seven-day column is the one that shows whether a
        task has actually stopped.
    </p>
</x-filament-panels::page>
