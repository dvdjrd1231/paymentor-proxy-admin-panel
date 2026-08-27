<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Pages\CronStats;
use App\Models\CronStat;
use App\Models\Setting;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * WHMCS's Automation Status: is the automation running, and what did it do.
 *
 * The reference's `automationstatus.php` answers one question before any other — *is this
 * thing running at all* — and shows the day's task activity underneath. Paymenter's Cron
 * Statistics page shows the activity but not the health: it will happily draw a tidy row of
 * zeroes for a scheduler that has been dead for a week, because a task that never ran
 * records nothing and nothing is exactly what zero looks like.
 *
 * That distinction stopped being cosmetic when `Others/TermLimits` arrived. Fixed-term
 * services are stopped by a sweep that runs **every minute**; if the scheduler is not
 * running, daily proxies quietly go on working past their term and nothing anywhere says
 * so. This page is where that shows up.
 *
 * Two independent clocks, which is the whole point:
 *
 * - **`last_scheduler_run`** — stamped every minute by core's heartbeat command. This is
 *   the one that says whether `php artisan schedule:run` is in cron at all.
 * - **`last_cron_run`** — stamped by `app:cron-job` when the daily pass finishes. A fresh
 *   heartbeat with a stale daily run means the scheduler is up but the daily job is
 *   failing, which is a different fault with a different fix.
 *
 * Read-only. Nothing here changes anything; the actions link to the pages that do.
 *
 * @link docs/02b-admin-area.md
 */
class AutomationStatus extends Page
{
    protected string $view = 'adminops::pages.automation-status';

    protected static ?string $slug = 'automation-status';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * The heartbeat is stamped every minute, so anything past this is a scheduler that is
     * not running. Five minutes rather than two: a loaded box can miss a tick, and an alarm
     * that cries wolf is an alarm nobody reads.
     */
    private const HEARTBEAT_STALE_MINUTES = 5;

    /** The daily job runs once a day, so a day and a half without one is a real failure. */
    private const DAILY_STALE_HOURS = 36;

    /**
     * The reference's **Daily Actions** tiles, in its order and its words.
     *
     * Each is `key => [title, past participle]` — the reference labels the figure with what
     * the task *did* ("0 Generated", "0 Suspended", "0 Terminated") rather than repeating the
     * task name, which is what makes a wall of zeroes readable at a glance.
     *
     * Two of these are ours. **Fixed Term Terminations** and **Cancellation Requests** are
     * tasks the reference has and core did not; they write to `cron_stats` like everything
     * else, so this page needs no knowledge of which extension owns them — a key that never
     * appears simply has no tile.
     *
     * `Overdue Terminations` and `Fixed Term Terminations` are deliberately separate, as they
     * are on the reference. One is a service whose paid period ran out unpaid; the other was
     * always going to end on a date. Merged, a fixed-term module that had stopped would hide
     * behind an overdue ladder that had not.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const TASKS = [
        'invoices_created' => ['Invoices', 'Generated'],
        'invoice_charged' => ['Payment Captures', 'Captured'],
        'cancellations_processed' => ['Cancellation Requests', 'Processed'],
        'services_suspended' => ['Overdue Suspensions', 'Suspended'],
        'services_terminated' => ['Overdue Terminations', 'Terminated'],
        'fixed_term_terminations' => ['Fixed Term Terminations', 'Terminated'],
        'orders_cancelled' => ['Unpaid Orders', 'Cancelled'],
        'upgrade_invoices_updated' => ['Upgrade Invoices', 'Updated'],
        'billable_items_invoiced' => ['Billable Items', 'Invoiced'],
        'quotes_expired' => ['Quotes', 'Expired'],
        'tickets_closed' => ['Inactive Tickets', 'Closed'],
        'email_logs_deleted' => ['Email Logs', 'Deleted'],
    ];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'Automation Status';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cronStats')
                ->label('Cron Statistics')
                ->icon(Heroicon::ChartBar)
                ->color('gray')
                ->url(fn (): string => CronStats::getUrl()),
        ];
    }

    protected function getViewData(): array
    {
        $heartbeat = $this->stamp('last_scheduler_run');
        $daily = $this->stamp('last_cron_run');

        return [
            'heartbeat' => [
                'at' => $heartbeat,
                'healthy' => $heartbeat && $heartbeat->diffInMinutes(now()) <= self::HEARTBEAT_STALE_MINUTES,
                'threshold' => self::HEARTBEAT_STALE_MINUTES . ' minutes',
            ],
            'daily' => [
                'at' => $daily,
                'healthy' => $daily && $daily->diffInHours(now()) <= self::DAILY_STALE_HOURS,
                'threshold' => self::DAILY_STALE_HOURS . ' hours',
            ],
            'nextRun' => $this->nextDailyRun(),
            'tasks' => $this->tasks(),
            'problems' => $this->problems($heartbeat, $daily),
        ];
    }

    /**
     * When the daily pass is next due — the reference's "Next Daily Task Run" tile.
     *
     * Derived from `cronjob_time` rather than stored, because nothing stores it: the
     * scheduler decides at run time whether the hour has come. Today's slot if it has not
     * passed, tomorrow's if it has.
     */
    private function nextDailyRun(): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', (string) config('settings.cronjob_time', '00:00')), 2, '0');

        $next = now()->setTime((int) $hour, (int) $minute, 0);

        return $next->isFuture() ? $next : $next->addDay();
    }

    /**
     * A timestamp written by the schedulers, or null if it has never been written.
     *
     * Both live in `settings` under `settingable_type = CronStat::class`, which is where
     * core puts them. Parsing is guarded: a malformed value should read as "unknown"
     * rather than take down the page that exists to report faults.
     */
    private function stamp(string $key): ?Carbon
    {
        $value = Setting::query()
            ->where('key', $key)
            ->where('settingable_type', CronStat::class)
            ->value('value');

        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What each task has done — today, and over the last week.
     *
     * The week matters more than the day here. `app:cron-job` runs once, at
     * `cronjob_time`, so before that hour every one of today's figures is legitimately
     * zero — a page that showed only today would report a healthy install as idle every
     * morning. The seven-day column is what tells you a task has genuinely stopped doing
     * anything.
     *
     * @return array<int, array{key: string, label: string, today: int, week: int, lastSeen: ?string}>
     */
    private function tasks(): array
    {
        $rows = CronStat::query()
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->get()
            ->groupBy('key');

        $today = now()->toDateString();
        $tiles = [];

        foreach (self::TASKS as $key => [$title, $did]) {
            $group = $rows->get($key);

            // A task that has never recorded anything gets no tile. The reference does the
            // same — its grid shows the tasks this install actually runs, so an uninstalled
            // module leaves no permanent zero claiming to be watched.
            if ($group === null) {
                continue;
            }

            $tiles[] = [
                'title' => $title,
                'did' => $did,
                'today' => (int) $group->where('date', $today)->sum('value'),
                'week' => (int) $group->sum('value'),
                // The reference puts a failed count on every tile, in red, beside the figure.
                'failed' => (int) ($rows->get($key . '_failed')?->where('date', $today)->sum('value') ?? 0),
                'lastSeen' => $group->max('date'),
            ];
        }

        return $tiles;
    }

    /**
     * The things worth saying out loud, worst first.
     *
     * Deliberately specific about the fix. "Automation is not running" sends somebody to
     * the wrong place; the command that is missing from cron is the useful sentence.
     *
     * @return array<int, array{title: string, body: string}>
     */
    private function problems(?Carbon $heartbeat, ?Carbon $daily): array
    {
        $problems = [];

        if (!$heartbeat) {
            $problems[] = [
                'title' => 'The scheduler has never run',
                'body' => 'Nothing has stamped a heartbeat. Add Laravel\'s scheduler to cron: '
                    . '* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1',
            ];
        } elseif ($heartbeat->diffInMinutes(now()) > self::HEARTBEAT_STALE_MINUTES) {
            $problems[] = [
                'title' => 'The scheduler has stopped',
                'body' => 'Last heartbeat ' . $heartbeat->diffForHumans() . '. Everything scheduled is '
                    . 'stopped, including the every-minute sweep that ends fixed-term services — daily and '
                    . 'weekly proxies are running past their term while this is true.',
            ];
        }

        if (!$daily) {
            $problems[] = [
                'title' => 'The daily job has never completed',
                'body' => 'No run of app:cron-job has finished. Renewal invoices, suspensions and '
                    . 'terminations all come from it.',
            ];
        } elseif ($daily->diffInHours(now()) > self::DAILY_STALE_HOURS) {
            $problems[] = [
                'title' => 'The daily job is overdue',
                'body' => 'Last completed ' . $daily->diffForHumans() . '. If the heartbeat above is healthy '
                    . 'the scheduler is fine and app:cron-job itself is failing — check the application log '
                    . 'rather than cron.',
            ];
        }

        return $problems;
    }
}
