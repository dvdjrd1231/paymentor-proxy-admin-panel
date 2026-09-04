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
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\UpdatePaymenter;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\UtilitiesCalendar;
use Paymenter\Extensions\Others\CurrencyRates\CurrencyRates;

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
     * The third element is the tile's glyph — the reference puts a grey icon in the top
     * right corner of every Daily Actions tile, one per task (issue #28).
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    /**
     * The reference's Daily Actions grid, tile for tile in its order (Leandro's
     * screenshot, 2026-09-04). A real task carries its cron_stats key; a concept this
     * store does not have carries a `disabled` reason instead and renders exactly the
     * way the reference renders its own disabled tiles — a dash and a grey "Disabled".
     * The reference-set tiles are `always` so a zero day still shows its zero, as the
     * reference's do; this install's extra tasks follow after and keep the old
     * only-if-ever-recorded rule.
     */
    private const TASKS = [
        'invoices_created' => ['Invoices', 'Generated', 'ri-file-text-line', 'always' => true],
        'late_fees' => ['Late Fees', '', 'ri-auction-line', 'disabled' => 'This store adds no late fees — an unpaid invoice suspends the service instead'],
        // The reference's own name for this tile ("Credit Card Charges"), not ours.
        'invoice_charged' => ['Credit Card Charges', 'Captured', 'ri-bank-card-line', 'always' => true],
        'invoice_reminders' => ['Invoice & Overdue Reminders', '', 'ri-mail-send-line', 'disabled' => 'Core mails an invoice once, when it is raised — there is no dunning reminder sequence'],
        'cancellations_processed' => ['Cancellation Requests', 'Processed', 'ri-close-circle-line', 'always' => true],
        'services_suspended' => ['Overdue Suspensions', 'Suspended', 'ri-notification-3-line', 'always' => true],
        'services_terminated' => ['Overdue Terminations', 'Terminated', 'ri-calendar-close-line', 'always' => true],
        'fixed_term_terminations' => ['Fixed Term Terminations', 'Terminated', 'ri-plug-line', 'always' => true],
        'domain_renewal_notices' => ['Domain Renewal Notices', '', 'ri-global-line', 'disabled' => 'No domain registrar is connected to this store'],
        'domain_transfer_sync' => ['Domain Transfer Status Synchronisation', '', 'ri-arrow-left-right-line', 'disabled' => 'No domain registrar is connected to this store'],
        'domain_status_sync' => ['Domain Status Synchronisation', '', 'ri-history-line', 'disabled' => 'No domain registrar is connected to this store'],
        'tickets_closed' => ['Inactive Tickets', 'Closed', 'ri-inbox-archive-line', 'always' => true],
        'affiliate_commissions' => ['Delayed Affiliate Commissions', '', 'ri-money-dollar-box-line', 'disabled' => 'Commissions credit the moment the referred invoice is paid — nothing is delayed'],
        'email_marketer_rules' => ['Email Marketer Rules', '', 'ri-mail-settings-line', 'disabled' => 'Campaigns send when an admin sends them — there is no rules engine'],
        'client_status_update' => ['Client Status Update', '', 'ri-arrow-up-down-line', 'disabled' => 'Paymenter keeps no client active/inactive flag to sweep'],
        // This install's own tasks, real and running, after the reference's set.
        'orders_cancelled' => ['Unpaid Orders', 'Cancelled', 'ri-shopping-cart-2-line'],
        'upgrade_invoices_updated' => ['Upgrade Invoices', 'Updated', 'ri-arrow-up-circle-line'],
        'billable_items_invoiced' => ['Billable Items', 'Invoiced', 'ri-money-dollar-circle-line'],
        'quotes_expired' => ['Quotes', 'Expired', 'ri-file-paper-2-line'],
        'email_logs_deleted' => ['Email Logs', 'Deleted', 'ri-mail-close-line'],
    ];

    /** How many days the reference's "This Week" view covers, inclusive of today. */
    private const CHART_DAYS = 7;

    /** The reference's "Viewing X ▾" picker — which task's daily figures the chart plots. */
    #[Url]
    public string $viewing = '';

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
        $tasks = $this->tasks();

        // Default to whatever the tile grid actually shows — the first task this install
        // has ever recorded — rather than a fixed key that might not exist here.
        if ($this->viewing === '' || !in_array($this->viewing, array_column($tasks, 'key'), true)) {
            $this->viewing = $tasks[0]['key'] ?? '';
        }

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
            'cronStatsUrl' => $this->safeCronStatsUrl(),
            'tasks' => $tasks,
            'problems' => $this->problems($heartbeat, $daily),
            'chart' => $this->chart(),
            'calendar' => $this->calendar(),
            'systemTasks' => $this->systemTasks(),
        ];
    }

    /**
     * The reference's second tier under Daily Actions — Database Backup, WHMCS Updates,
     * Currency Exchange Rates, Product Pricing Updates, Server Usage Stats — a status
     * line each, not a count. Paymenter has real ground truth for two of the five:
     *
     * - **Database Backup** — real, but never admin-visible: {@see \Paymenter\Extensions\Others\AdminOps\Admin\Pages\DatabaseStatus}'s
     *   own reasoning applies here too — backups run on the host via `scripts/backup` on
     *   a schedule, not a PHP request this page could report on. Honestly dead.
     * - **Platform Updates** — real: the same up-to-date check {@see UpdatePaymenter} makes.
     * - **Currency Exchange Rates** — real: whether `Others/CurrencyRates` is installed,
     *   which is the whole fact behind WHMCS's own green check — not a fabricated "last
     *   synced" timestamp, since price rows are updated in place and carry no such stamp.
     *
     * Product Pricing Updates and Server Usage Stats have no Paymenter counterpart at
     * all — omitted rather than shown as a permanent, meaningless "Disabled".
     *
     * @return array<int, array{label: string, icon: string, ok: bool, note: string}>
     */
    private function systemTasks(): array
    {
        $tasks = [];

        $tasks[] = [
            'label' => 'Database Backup',
            'icon' => 'ri-database-2-line',
            'ok' => false,
            'note' => 'Runs on the host via a scheduled task, not tracked here',
        ];

        try {
            $current = UpdatePaymenter::currentVersion();
            // Cache::remember() (UpdatePaymenter::latest()) stores {version, checkedAt} —
            // the version string alone is what's compared here.
            $cached = Cache::get('adminops.latest-version');
            $latest = is_array($cached) ? ($cached['version'] ?? null) : null;
            $upToDate = $latest === null || version_compare($current, $latest, '>=');
            $tasks[] = [
                'label' => 'Platform Updates',
                'icon' => 'ri-download-2-line',
                'ok' => $upToDate,
                'note' => $upToDate ? 'Paymenter is up to date.' : "Version {$latest} is available.",
            ];
        } catch (\Throwable) {
        }

        $tasks[] = [
            'label' => 'Currency Exchange Rates',
            'icon' => 'ri-exchange-line',
            'ok' => class_exists(CurrencyRates::class),
            'note' => class_exists(CurrencyRates::class) ? 'Enabled — syncs hourly.' : 'Disabled',
        ];

        // The reference's last two system tiles (Leandro's screenshot, 2026-09-04).
        // Product pricing IS the rates sync here — Paymenter stores a price per
        // currency, so updating the rate rewrites the prices in the same pass.
        $tasks[] = [
            'label' => 'Product Pricing Updates',
            'icon' => 'ri-price-tag-3-line',
            'ok' => class_exists(CurrencyRates::class),
            'note' => class_exists(CurrencyRates::class)
                ? 'Secondary-currency prices rewrite with each rates sync.'
                : 'Disabled',
        ];
        $tasks[] = [
            'label' => 'Server Usage Stats',
            'icon' => 'ri-server-line',
            'ok' => false,
            'note' => 'The proxy panel reports usage on its own dashboard, not here.',
        ];

        return $tasks;
    }

    /** The verdict tile's "View cron status" link target, or null when unroutable. */
    private function safeCronStatsUrl(): ?string
    {
        try {
            return CronStats::getUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The reference's month calendar beside Daily Actions: the current month's real
     * weeks, today highlighted, each day a link to the Calendar page.
     *
     * @return array{month: string, weeks: array<int, array<int, array{n: int, other: bool, today: bool}>>, url: ?string}
     */
    private function calendar(): array
    {
        $today = now();
        $cursor = $today->copy()->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $end = $today->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);

        $weeks = [];
        while ($cursor <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'n' => $cursor->day,
                    'other' => !$cursor->isSameMonth($today),
                    'today' => $cursor->isToday(),
                ];
                $cursor = $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $url = null;
        try {
            $url = UtilitiesCalendar::getUrl();
        } catch (\Throwable $e) {
        }

        return ['month' => $today->format('F Y'), 'weeks' => $weeks, 'url' => $url];
    }

    /**
     * The reference's "Viewing X / This Week" chart: the picked task's real daily count,
     * one point per of the last seven days, zero-filled for a day nothing ran.
     *
     * @return array{label: string, did: string, days: array<int, array{date: string, label: string, value: int}>}
     */
    private function chart(): array
    {
        [$label, $did] = self::TASKS[$this->viewing] ?? ['—', '', ''];

        // Grouped by a plain formatted string, not the column directly: `date` is cast to
        // Carbon, so grouping by the raw attribute keys the result by that Carbon object's
        // own __toString() ("2026-08-31 00:00:00"), which the lookup below — a bare
        // toDateString() — never matches. Every day silently read as zero until this was
        // pinned down with a planted row that should have moved the line and did not.
        $rows = CronStat::query()
            ->where('key', $this->viewing)
            ->where('date', '>=', now()->subDays(self::CHART_DAYS - 1)->toDateString())
            ->get()
            ->groupBy(fn (CronStat $row) => $row->date->toDateString());

        $days = [];
        for ($i = self::CHART_DAYS - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('jS'),
                'value' => (int) ($rows->get($date->toDateString())?->sum('value') ?? 0),
            ];
        }

        return ['label' => $label, 'did' => $did, 'days' => $days];
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

        foreach (self::TASKS as $key => $task) {
            [$title, $did, $icon] = $task;

            // A concept this store does not have: the reference's own disabled-tile
            // treatment — a dash, a grey "Disabled", the reason on the hover.
            if (isset($task['disabled'])) {
                $tiles[] = [
                    'key' => $key, 'title' => $title, 'did' => '', 'icon' => $icon,
                    'today' => null, 'week' => null, 'failed' => 0, 'lastSeen' => null,
                    'disabled' => $task['disabled'],
                ];

                continue;
            }

            $group = $rows->get($key);

            // The reference-set tiles always render (a zero day shows its zero, as the
            // reference's do). This install's extra tasks keep the old rule: never
            // recorded anything, no tile — an uninstalled module must not leave a
            // permanent zero claiming to be watched.
            if ($group === null && !isset($task['always'])) {
                continue;
            }

            // Collection::where('date', $today) compares a Carbon object (the cast column)
            // against a bare "Y-m-d" string with PHP's == — which stringifies the Carbon side
            // as "Y-m-d H:i:s" first, so it never matched and every "today" figure has always
            // read zero regardless of what actually ran. filter() with an explicit
            // ->toDateString() on each row is what the comparison needed.
            $isToday = fn ($row) => $row->date->toDateString() === $today;

            $tiles[] = [
                'key' => $key,
                'title' => $title,
                'did' => $did,
                'icon' => $icon,
                'today' => (int) $group?->filter($isToday)->sum('value'),
                'week' => (int) $group?->sum('value'),
                // The reference puts a failed count on every tile, in red, beside the figure.
                'failed' => (int) ($rows->get($key . '_failed')?->filter($isToday)->sum('value') ?? 0),
                'lastSeen' => $group?->max('date'),
                'disabled' => null,
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
