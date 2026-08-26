<?php

namespace Paymenter\Extensions\Others\TermLimits;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\TermLimits\Console\EnforceTerms;
use Paymenter\Extensions\Others\TermLimits\Support\Sweeper;
use Paymenter\Extensions\Others\TermLimits\Support\Terms;
use Throwable;

/**
 * Fixed-term products: daily and weekly proxies that end when their time is up.
 *
 * ## The problem this exists to fix
 *
 * Paymenter's daily and weekly plans are `one-time`, and `Service::calculateNextDueDate()`
 * returns **null** for a one-time plan — so an activated daily service is stored with
 * `expires_at = NULL`. Every branch of core's cron that would end it is a comparison
 * against that column:
 *
 * ```php
 * Service::where('status', 'active')->where('expires_at', '<', now()->subDays(2))   // suspend
 * Service::where('status', 'suspended')->where('expires_at', '<', now()->subDays(14)) // terminate
 * ```
 *
 * `NULL < anything` is never true in SQL, so **neither ever matches**. A daily proxy runs
 * for ever: the customer pays for one day and keeps it. There is no renewal invoice either,
 * which is correct and is why it went unnoticed — the service simply never ends.
 *
 * On this store that is 10 daily and 10 weekly products.
 *
 * ## What this does instead
 *
 * A clock per service, in hours, kept beside `services` rather than in it — `expires_at` is
 * cast to `date` in core, and a daily product measured to the day runs between one and two
 * days. It starts when the service goes live, not when it was ordered: the brief says
 * "usage hours equivalent to the contracted period", and an order that waited overnight for
 * provisioning has not used any of them.
 *
 * A sweeper runs **every minute** and stops what is due. Core's daily cron is the right
 * cadence for a monthly product and the wrong one for a daily one — swept at midnight, a
 * service bought at 14:00 gets either ten hours too few or fourteen too many.
 *
 * **Non-renewable is by construction, not by rule.** Nothing here writes `expires_at`, so
 * core's invoicing branch still cannot see these services and still creates no renewal
 * invoice. Monthly products are untouched and go on renewing exactly as they do now.
 *
 * Extensions of time are an admin action with a **required reason**, appended to a record
 * that is never edited — the brief allows extra time "based on specific, justifiable needs
 * regarding maintenance or downtime", and an extension nobody can account for afterwards is
 * the thing that record exists to prevent.
 *
 * @link docs/modules/term-limits.md
 */
#[ExtensionMeta(
    name: 'Fixed-Term Products',
    description: 'Daily and weekly products end when their contracted time is up; monthly products still renew.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class TermLimits extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Applies to <b>one-time</b> plans with a period of hours, days or weeks — the daily and '
                    . 'weekly products. Monthly (recurring) products are not affected and still renew. '
                    . 'Without this, a one-time service is stored with no expiry date and '
                    . '<b>never ends</b>. See <code>docs/modules/term-limits.md</code>.'
                ),
            ],
            [
                'name' => 'terminate_on_expiry',
                'label' => 'When the time is up',
                'type' => 'select',
                'options' => [
                    'terminate' => 'Terminate — release the proxies back to the panel',
                    'suspend' => 'Suspend — keep the service recoverable',
                ],
                'default' => 'terminate',
                'description' => 'Terminating returns capacity to the panel immediately, which is the point of a '
                    . 'fixed term. Suspending leaves the service recoverable if the customer comes back with a '
                    . 'good reason an hour later.',
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/TermLimits/database/migrations');

        // Services sold before today have no clock. Without this they keep running for ever,
        // which is the situation this module exists to end — but each is given a full term
        // from *now* rather than from its order date, because a customer who has had an
        // unmetered proxy for three weeks through no fault of their own should not lose it
        // the moment somebody ticks a box.
        try {
            $opened = Sweeper::backfill();

            Log::info('TermLimits installed: opened ' . $opened . ' terms for services already running.');
        } catch (Throwable $exception) {
            // Never let a backfill failure block the install: the extension is still correct
            // for everything sold from now on, and the backfill can be re-run by hand.
            Log::error('TermLimits: backfill failed', ['exception' => $exception->getMessage()]);
        }
    }

    public function uninstalled()
    {
        // The terms go with it. Leaving the tables would leave a clock nothing reads, and
        // re-enabling later would find stale rows claiming services expired months ago.
        ExtensionHelper::rollbackMigrations('extensions/Others/TermLimits/database/migrations');
    }

    public function boot()
    {
        View::addNamespace('termlimits', __DIR__ . '/resources/views');

        $this->startTheClockOnActivation();
        $this->sweepEveryMinute();
    }

    /**
     * The clock starts when the service becomes active.
     *
     * An Eloquent `updated` hook rather than a domain event: core defines
     * `App\Events\Service\Updated` but never dispatches it, so listening for it would be
     * listening for something that does not happen. `saved` covers every path to activation
     * — first payment, an admin flipping the status, a renewal of a service that had been
     * suspended — and {@see Terms::open()} is keyed on the service, so being called more
     * than once for the same service does nothing the second time.
     */
    private function startTheClockOnActivation(): void
    {
        Service::saved(function (Service $service): void {
            if ($service->status !== Service::STATUS_ACTIVE) {
                return;
            }

            try {
                Terms::open($service);
            } catch (Throwable $exception) {
                // A service that activates is worth more than a clock that starts. The
                // sweeper will not stop it, and the backfill can open the term later.
                Log::error('TermLimits: could not open a term for service #' . $service->id, [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * Registered against the scheduler that already runs every minute on the server, so
     * there is no second cron entry to install or forget.
     *
     * `withoutOverlapping` matters more here than in an hourly job: a sweep that takes
     * longer than a minute — a slow panel, a long queue — would otherwise be joined by the
     * next one and both would try to terminate the same services.
     */
    private function sweepEveryMinute(): void
    {
        // Registered against the scheduler that already runs every minute on this server,
        // so there is no second cron entry to install or to forget. A closure rather than
        // `->command()` because the command is registered by this extension too, and a
        // scheduled command that has not been resolved yet is a scheduler that fails
        // silently — the closure calls the same code either way.
        app()->booted(function (): void {
            Artisan::starting(fn ($artisan) => $artisan->resolve(EnforceTerms::class));

            app(Schedule::class)
                ->call(fn () => Sweeper::run())
                ->everyMinute()
                ->name('term-limits-enforce')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
