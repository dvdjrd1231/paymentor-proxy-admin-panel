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
 */
class UpdatePaymenter extends Page
{
    protected string $view = 'adminops::pages.update-paymenter';

    protected static ?string $slug = 'update-paymenter';

    /** Navigation is built by {@see \Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The upstream release the vendored core sits at. */
    public const VENDORED_BASE = '1.5.8';

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

    /** The blue button, which examines the release rather than applying it. */
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

        // No To-Do row any more. Queueing one was the wrong answer to "what does this
        // button do": it made a button labelled Update look like it had scheduled an
        // update, when all it had done was write a reminder (Leandro, 2026-09-07: "Why
        // update function is queue into TO-DO list? it was downloaded but the version is
        // not changed"). The plan is the deliverable; the button now says so.
        $this->buildPlan();
    }

    /** The plan for the release, once built — see {@see Support\ReleasePlan}. */
    public array $plan = [];

    public bool $planning = false;

    /**
     * Download the newest release and work out exactly which core files it would change
     * (Leandro, 2026-09-07: "update only possible and necessary files without changing
     * functions and designs").
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
