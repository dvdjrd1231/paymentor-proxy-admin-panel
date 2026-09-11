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

    /** Daily, not every minute. */
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
