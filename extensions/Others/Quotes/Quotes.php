<?php

namespace Paymenter\Extensions\Others\Quotes;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\Quotes\Support\Quoting;
use Throwable;

/**
 * Quotes: a priced proposal a customer can accept, which then becomes an invoice.
 *
 * Paymenter has no document that is not already a bill. An invoice is `pending`, `paid` or
 * `cancelled`, and none of those can stand in for "here is what it would cost" without
 * misrepresenting a proposal as a debt: the customer sees it among their invoices, the
 * overdue ladder starts counting, and a reminder goes out for money nobody agreed to pay.
 *
 * So a quote is its own record with its own life — draft, sent, then accepted, declined or
 * expired — and becomes an invoice only at the moment somebody accepts it. Every transition
 * is one-way and guarded on the state before it, which is the whole safety of the feature:
 * an accepted quote creates a real invoice, and creating two creates a debt that does not
 * exist.
 *
 * The client-area page was already waiting for this. `Others/ClientTools` shipped a Quotes
 * screen with an empty state and a note saying *"a future quoting extension only has to fill
 * this collection"* — this is that extension, and it fills it.
 *
 * @link docs/modules/quotes.md
 */
#[ExtensionMeta(
    name: 'Quotes',
    description: 'Priced proposals a customer can accept, which become invoices.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class Quotes extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString(
                'Written under <b>Billing → Quotes</b>. A quote is invisible to the customer until it is '
                . '<b>sent</b>, and can only be edited while it is a draft — once sent it is a document they '
                . 'are looking at. Accepting it raises a real invoice for the same lines.'
            ),
        ]];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/Quotes/database/migrations');
    }

    public function uninstalled()
    {
        // Quotes go; the invoices accepted ones produced are core's and stay. An accepted
        // quote has already done its job, and its invoice is the record that matters.
        ExtensionHelper::rollbackMigrations('extensions/Others/Quotes/database/migrations');
    }

    public function boot()
    {
        $this->expireDaily();
    }

    /**
     * Daily, not every minute.
     *
     * A quote carries a *date*, not a clock, so expiring one at 00:04 rather than 00:00
     * changes nothing for anybody — and a customer who accepts at one minute past midnight
     * on the closing day has done what was asked of them. Losing that sale to a scheduler
     * would be a self-inflicted wound.
     */
    private function expireDaily(): void
    {
        app()->booted(function (): void {
            // Guarded because a throw here reaches no handler: `booted()` runs on every
            // request, so an exception while *registering* background work 500s every page
            // of the site — which is exactly what an `Artisan::starting()` that did not
            // exist did on 2026-08-27. A schedule that fails to register costs a background
            // task; an unhandled boot exception costs the whole business.
            try {
                app(Schedule::class)
                    ->call(fn () => Quoting::sweep())
                    ->dailyAt(config('settings.cronjob_time', '00:00'))
                    ->name('quotes-expire')
                    ->withoutOverlapping()
                    ->onOneServer();
            } catch (Throwable $exception) {
                Log::error('Quotes: could not register its scheduled work', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
