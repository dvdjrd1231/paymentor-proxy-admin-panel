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
     * Names for tasks core does not know about, because they belong to extensions.
     *
     * Worded as the reference words its own — its Automation Status lists a
     * "Cancellation Requests" task — so the two screens read the same way round.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'cancellations_processed' => 'Cancellation Requests',
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
            'tasks' => $this->tasks(),
            'problems' => $this->problems($heartbeat, $daily),
        ];
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
            ->get();

        return $rows
            ->groupBy('key')
            ->map(function ($group, string $key): array {
                $today = $group->where('date', now()->toDateString())->sum('value');

                return [
                    'key' => $key,
                    // Core already names these for its own cron page; reusing the same
                    // strings means the two screens cannot drift apart.
                    'label' => self::LABELS[$key]
                        ?? (__('admin.cronjob.' . $key) === 'admin.cronjob.' . $key
                            ? str($key)->replace('_', ' ')->ucfirst()->toString()
                            : __('admin.cronjob.' . $key)),
                    'today' => (int) $today,
                    'week' => (int) $group->sum('value'),
                    'lastSeen' => $group->max('date'),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
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
