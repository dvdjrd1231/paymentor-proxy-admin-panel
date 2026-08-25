<?php

namespace Paymenter\Extensions\Others\AdminOps;

use App\Admin\Resources\ServiceCancellationResource;
use App\Admin\Resources\ServiceResource;
use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;

/**
 * Admin-area usability, built to the WHMCS admin the client asked us to match.
 *
 * Paymenter's admin is a competent Filament panel, but it is organised around records
 * rather than around a working day: the dashboard reports 30-day trends, and answering
 * "what needs doing" or "who is this customer" means visiting several lists. This adds the
 * two things WHMCS gets right and Paymenter does not —
 *
 *  - a homepage that opens with today's figures, the work queue and the usual shortcuts;
 *  - a client summary, everything about one customer on one screen.
 *
 * All of it is additive. Nothing here replaces a core page, so disabling the extension
 * returns the panel to stock Paymenter — the one core touch is the **Summary** link on the
 * customer list, which is guarded by `class_exists` and documented as touchpoint #10.
 *
 * Adding that link from here was tried first and does not work: `Table::configureUsing()`
 * runs inside `Table::make()`, and the resource's own `table()` method runs afterwards with
 * `->recordActions([...])`, which resets the array before repopulating it. Anything pushed
 * from an extension is therefore discarded before the table renders. The same is true of
 * `->filters([...])`, which is why the action queue links to core's existing filters rather
 * than adding its own.
 *
 * @link docs/02b-admin-area.md
 */
#[ExtensionMeta(
    name: 'Admin Operations',
    description: 'WHMCS-style admin dashboard and per-customer summary screen.',
    version: '2.0.0',
    author: 'Paymenter Proxy Platform',
)]
class AdminOps extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString(
                'Adds the WHMCS-style admin dashboard (<b>At a glance</b>, <b>Needs attention</b>, '
                . 'shortcuts) and a per-customer <b>Summary</b> screen, reached from the '
                . '<b>Summary</b> link on each row of Clients. '
                . 'See <code>docs/02b-admin-area.md</code>.'
            ),
        ]];
    }

    public function boot()
    {
        View::addNamespace('adminops', __DIR__ . '/resources/views');

        $this->registerStyles();
        $this->registerQueueNavigation();
        $this->keepTheDailyLogWritable();
    }

    /**
     * WHMCS's sidebar queues: the filtered service lists staff live in, always one click
     * away and carrying their count.
     *
     * Deliberately only two. Core already badges **Invoices** with the unpaid count and
     * **Tickets** with the open count, and its ticket list opens on the Open tab by default,
     * so "unpaid invoices" and "awaiting reply" entries would put the same two numbers in
     * the sidebar twice — clutter, and the opposite of what was asked for. Services is the
     * gap: no badge, no default filter, and the two states that actually need chasing in a
     * provisioning business are a backlog waiting to be set up and an account suspended for
     * non-payment.
     *
     * Registered through `Filament::serving()` because the panel does not exist yet when
     * extensions boot. Unlike record actions and table filters, navigation items *are*
     * addable from outside the panel — `navigationItems()` appends rather than resets.
     */
    private function registerQueueNavigation(): void
    {
        Filament::serving(function (): void {
            if (!ServiceResource::canViewAny()) {
                return;
            }

            $items = [
                NavigationItem::make('Pending services')
                    ->icon('heroicon-o-clock')
                    ->group('Queues')
                    ->sort(1)
                    ->url(fn (): string => ServiceResource::getUrl('index', [
                        'filters' => ['status' => ['value' => 'pending']],
                    ]))
                    ->badge(fn () => static::badge(Metrics::servicesPending()), color: 'info'),

                NavigationItem::make('Suspended services')
                    ->icon('heroicon-o-pause-circle')
                    ->group('Queues')
                    ->sort(2)
                    ->url(fn (): string => ServiceResource::getUrl('index', [
                        'filters' => ['status' => ['value' => 'suspended']],
                    ]))
                    ->badge(fn () => static::badge(Metrics::servicesSuspended()), color: 'warning'),
            ];

            // Cancellation requests live inside the Services cluster, two clicks in and with
            // no badge, so a request sat waiting is invisible until somebody goes looking.
            // Checked separately because the resource has its own policy: a role that may
            // see services is not automatically allowed these.
            if (ServiceCancellationResource::canViewAny()) {
                $items[] = NavigationItem::make('Pending cancellations')
                    ->icon('heroicon-o-no-symbol')
                    ->group('Queues')
                    ->sort(3)
                    ->url(fn (): string => ServiceCancellationResource::getUrl('index'))
                    ->badge(fn () => static::badge(Metrics::cancellationsPending()), color: 'danger');
            }

            Filament::getCurrentOrDefaultPanel()?->navigationItems($items);
        });
    }

    /**
     * A badge shows a number or nothing — never a zero.
     *
     * Filament hides the badge entirely for null, which is what an empty queue should look
     * like: a grey "0" on four menu items reads as clutter you learn to ignore, and the
     * whole point of these is that a number here means something needs doing.
     *
     * Counted once per request: the sidebar renders on every admin page, and each of these
     * is a `COUNT` over a table that is not indexed on `status`.
     */
    private static function badge(int $count): ?string
    {
        return $count > 0 ? (string) $count : null;
    }

    /**
     * Ship the widgets' CSS in the panel head.
     *
     * A render hook rather than a `<style>` block inside each widget view: Livewire
     * components must have a single root element, and polling re-renders the component, so
     * a style tag living in a widget would either break the root or be re-sent on every
     * poll.
     */
    private function registerStyles(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => Blade::render('@include(\'adminops::styles\')'),
        );
    }

    /**
     * Force the day's log file to be group-writable.
     *
     * Whichever process writes the first line of the day owns the file. The scheduler and
     * artisan run as root, web requests as nginx, so on a day root got there first nginx
     * could not append — and since logging is part of handling the request, that surfaced
     * as 500s on the order pages with nothing recorded, the logger being what broke.
     *
     * Set here rather than in config/logging.php (where it also is) because config/ is not
     * bind-mounted into the container and extensions/ is. Remove once ./config is mounted.
     * Setting the mode beats chmod: it applies to whoever creates the file, and a chmod
     * only holds until midnight.
     */
    private function keepTheDailyLogWritable(): void
    {
        if (config('logging.channels.daily.permission') === null) {
            config(['logging.channels.daily.permission' => 0666]);
        }
    }
}
