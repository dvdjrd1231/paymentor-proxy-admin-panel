<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * WHMCS's Update WHMCS screen (issue #27): the verdict line, then the two big version
 * tiles — Your Version in grey, Latest Version in blue — with the release links under
 * them. Core's own Updates page stays reachable and untouched; this one exists because
 * the reference's screen is the one the staff know how to read.
 *
 * ## Why core says "development", and what this page says instead
 *
 * Core ships `app.version = development` because this install runs from source rather
 * than a tagged release tarball — the string is core's own, not a warning. Leandro's
 * instruction on the issue is that production should always present production versions,
 * the way WHMCS does. So this page reports the *upstream release line* honestly:
 *
 * - **Your Version** is {@see self::VENDORED_BASE}, the upstream release the vendored
 *   core was taken after, with the deployed source commit under it. That constant is
 *   maintained by hand and belongs to the vendoring commit — re-vendor core, update it.
 * - **Latest Version** comes from the same endpoint core's own update checker uses,
 *   `https://api.paymenter.org/version`, cached for six hours so the page never hangs
 *   on a slow upstream.
 *
 * Updating itself is not a button here. This install updates by vendoring upstream into
 * the repository and deploying — core's web updater would overwrite source-controlled
 * files in place and drift the server from git — so Update Now is an honestly-dead
 * control whose title says exactly that, the accepted convention for the reference's
 * controls this deployment cannot offer.
 */
class UpdatePaymenter extends Page
{
    protected string $view = 'adminops::pages.update-paymenter';

    protected static ?string $slug = 'update-paymenter';

    /** Navigation is built by {@see \Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * The upstream release the vendored core sits after. The vendor commit (c71388c,
     * 2026-07-17) took upstream master between v1.5.6 (Jun 30) and v1.5.7 (Jul 25), so
     * the release line this install carries is 1.5.6 plus master commits. Update this
     * alongside any re-vendor of core.
     */
    public const VENDORED_BASE = '1.5.6';

    /** How long a fetched latest-version answer is trusted before asking again. */
    private const CACHE_HOURS = 6;

    private const CACHE_KEY = 'adminops.latest-version';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.updates.update');
    }

    public function getTitle(): string
    {
        return 'Update Paymenter';
    }

    /**
     * The blue Update Now button, real (Leandro, 2026-09-05: "why doesn't it work?").
     *
     * On this install an update is not a file overwrite — releases are vendored into
     * the repository, reviewed, and shipped through the pipeline, and a web process
     * overwriting its own source would bypass exactly that review. What the button CAN
     * honestly do is start the process: re-check the version and, when one is behind,
     * put "Update Paymenter to X" on the To-Do List (due today) so the deployment run
     * is queued and visible — the same list the dashboard surfaces.
     */
    public function updateNow(): void
    {
        Cache::forget(self::CACHE_KEY);
        $latest = $this->latest()['version'];
        $current = self::currentVersion();

        if ($latest === null) {
            Notification::make()->title('Could not reach the release feed')
                ->body('Try again in a moment — the version service did not answer.')->danger()->send();

            return;
        }

        if (version_compare($current, $latest, '>=')) {
            Notification::make()->title('Already up to date')
                ->body("Version {$current} is the newest release.")->success()->send();

            return;
        }

        $title = "Update Paymenter to {$latest}";

        if (!\Illuminate\Support\Facades\Schema::hasTable('ext_todos')) {
            Notification::make()->title('The To-Do List is not migrated on this install')->danger()->send();

            return;
        }

        // One queue entry per release, not one per click.
        $exists = \Illuminate\Support\Facades\DB::table('ext_todos')
            ->where('title', $title)->where('done', false)->exists();

        if (!$exists) {
            \Illuminate\Support\Facades\DB::table('ext_todos')->insert([
                'title' => $title,
                'due_date' => now()->toDateString(),
                'admin_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Notification::make()->title("Update to {$latest} queued")
            ->body('Added to the To-Do List. The release is vendored, reviewed and shipped through the deployment pipeline — this page never overwrites files on its own.')
            ->persistent()
            ->success()->send();

        $this->buildPlan();
    }

    /** The plan for the release, once built — see {@see Support\ReleasePlan}. */
    public array $plan = [];

    public bool $planning = false;

    /**
     * Download the newest release and work out exactly which core files it would change
     * (Leandro, 2026-09-07: "update only possible and necessary files without changing
     * functions and designs").
     *
     * This is the honest form of that request on this deployment. Core's own updater
     * unpacks a release over the installation wholesale, which here would overwrite the
     * fourteen modifications `docs/CORE-TOUCHPOINTS.md` records and leave the server's
     * git tree diverged from the repository — the two outcomes his sentence rules out.
     * So the download is real, and what comes back is the file-by-file answer to "what is
     * actually necessary": how much is already identical, what may be taken safely, and
     * the short list of files carrying our own changes that a person has to merge.
     */
    public function buildPlan(): void
    {
        $latest = $this->latest()['version'];

        if (!$latest) {
            Notification::make()->title('Upstream could not be reached')->danger()->send();

            return;
        }

        $this->planning = true;
        $this->plan = (new \Paymenter\Extensions\Others\AdminOps\Support\ReleasePlan($latest))->build();
        $this->planning = false;

        if ($this->plan['error'] ?? null) {
            Notification::make()->title('Could not read the release')
                ->body($this->plan['error'])->danger()->send();

            return;
        }

        $counts = $this->plan['counts'];

        Notification::make()->title('Release ' . $latest . ' examined')
            ->body(sprintf(
                '%d file(s) already identical, %d safe to take, %d new, %d carrying our own changes.',
                $counts['same'] ?? 0, $counts['changed'] ?? 0, $counts['new'] ?? 0, $counts['touchpoint'] ?? 0,
            ))->success()->send();
    }

    public function clearPlan(): void
    {
        $this->plan = [];
    }

    /** The reference's top-right button; a real re-check rather than a dead control. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkNow')
                ->label('Check for Updates Now')
                ->color('gray')
                ->action(function (): void {
                    Cache::forget(self::CACHE_KEY);
                    $this->latest();

                    Notification::make()->title('Checked for updates')->success()->send();
                }),
        ];
    }

    /**
     * What is currently running, as a release line plus the deployed commit — the same
     * short hash the sidebar's System Information shows, resolved by the Rail.
     */
    public static function currentVersion(): string
    {
        $version = (string) config('app.version');

        return $version !== 'development' && $version !== '' ? $version : self::VENDORED_BASE;
    }

    /**
     * The newest stable release upstream publishes, from the endpoint core's own
     * `app:check-for-updates` reads. Null when upstream cannot be reached — the view
     * says so instead of pretending.
     *
     * @return array{version: ?string, checkedAt: ?\Carbon\Carbon}
     */
    private function latest(): array
    {
        $cached = Cache::remember(self::CACHE_KEY, now()->addHours(self::CACHE_HOURS), function (): array {
            try {
                $answer = Http::timeout(6)->get('https://api.paymenter.org/version')->json();

                return [
                    'version' => is_string($answer['latest'] ?? null) ? $answer['latest'] : null,
                    'checkedAt' => now()->toIso8601String(),
                ];
            } catch (\Throwable) {
                return ['version' => null, 'checkedAt' => now()->toIso8601String()];
            }
        });

        return [
            'version' => $cached['version'],
            'checkedAt' => $cached['checkedAt'] ? \Carbon\Carbon::parse($cached['checkedAt']) : null,
        ];
    }

    protected function getViewData(): array
    {
        $latest = $this->latest();
        $current = self::currentVersion();

        // The deployed source commit, resolved the same way the sidebar does it.
        $commit = null;
        try {
            $label = \Paymenter\Extensions\Others\AdminOps\Support\Rail::systemInformation()['Paymenter'] ?? null;
            if (is_string($label) && str_starts_with($label, 'source @ ')) {
                $commit = substr($label, 9);
            }
        } catch (\Throwable) {
        }

        return [
            'current' => $current,
            'commit' => $commit,
            'latest' => $latest['version'],
            'checkedAt' => $latest['checkedAt'],
            'upToDate' => $latest['version'] !== null
                && version_compare($current, $latest['version'], '>='),
            'releaseNotesUrl' => $latest['version']
                ? 'https://github.com/Paymenter/Paymenter/releases/tag/v' . $latest['version']
                : 'https://github.com/Paymenter/Paymenter/releases',
            'changelogUrl' => 'https://github.com/Paymenter/Paymenter/releases',
        ];
    }
}
