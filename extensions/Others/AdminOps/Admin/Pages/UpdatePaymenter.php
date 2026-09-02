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
