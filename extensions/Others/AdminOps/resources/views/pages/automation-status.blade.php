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
    <div class="ao-auto-head">
        <div class="ao-auto-head-tile {{ ($heartbeat['healthy'] && $daily['healthy']) ? 'ao-auto-ok' : 'ao-auto-bad' }}">
            <span class="ao-auto-head-figure">
                {{ ($heartbeat['healthy'] && $daily['healthy']) ? 'OK' : 'Error' }}
            </span>
            <span class="ao-auto-head-label">
                {{ ($heartbeat['healthy'] && $daily['healthy'])
                    ? 'Automation is running'
                    : 'See below to resolve' }}
            </span>
        </div>

        <div class="ao-auto-head-tile ao-auto-neutral">
            <span class="ao-auto-head-figure">
                {{ $daily['at']?->diffForHumans(short: true) ?? 'Never' }}
            </span>
            <span class="ao-auto-head-label">Last Daily Run</span>
        </div>

        <div class="ao-auto-head-tile ao-auto-quiet">
            <span class="ao-auto-head-figure">{{ $nextRun->diffForHumans(short: true) }}</span>
            <span class="ao-auto-head-label">
                Next Daily Task Run · {{ $nextRun->format('H:i') }}
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

    {{--
        The heartbeat gets a line of its own rather than a tile. It is not one of the daily
        actions — it is the thing that makes all of them possible — and it is the only clock
        on this page measured in minutes.
    --}}
    <p class="ao-auto-heartbeat {{ $heartbeat['healthy'] ? 'ao-auto-ok' : 'ao-auto-bad' }}">
        <span class="ao-auto-dot"></span>
        Scheduler heartbeat:
        <strong>{{ $heartbeat['healthy'] ? 'running' : 'not running' }}</strong>
        @if ($heartbeat['at'])
            · last seen {{ $heartbeat['at']->diffForHumans() }}
        @else
            · never seen
        @endif
        <span class="ao-auto-exact">(stale after {{ $heartbeat['threshold'] }})</span>
    </p>

    <h3 class="ao-auto-section">Daily Actions</h3>

    @if (empty($tasks))
        <p class="ao-catalogue-count">
            No task has recorded anything in the last seven days, which on a working install
            only happens on a brand new one.
        </p>
    @else
        <div class="ao-auto-tiles">
            @foreach ($tasks as $task)
                <div class="ao-auto-tile">
                    <div class="ao-auto-tile-head">{{ $task['title'] }}</div>
                    <div class="ao-auto-tile-body">
                        <span class="ao-auto-tile-figure">{{ number_format($task['today']) }}</span>
                        <span class="ao-auto-tile-did">{{ $task['did'] }}</span>
                        @if ($task['failed'] > 0)
                            <span class="ao-auto-tile-failed">{{ number_format($task['failed']) }} Failed</span>
                        @endif
                    </div>
                    <div class="ao-auto-tile-foot">
                        {{ number_format($task['week']) }} in the last 7 days
                    </div>
                </div>
            @endforeach
        </div>
    @endif

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
