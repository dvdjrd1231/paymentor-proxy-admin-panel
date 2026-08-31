<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * WHMCS's Reports wing: the category grid of its landing page and the data behind every
 * report Paymenter can honestly answer. One registry, one data method per report — the
 * landing page, the dropdown, the rail and the report screen all read the same list, so
 * none of them can disagree about what exists.
 *
 * A report is "real" when its numbers come from tables this install actually writes.
 * The rest render as the reference's pills but disabled, each saying why.
 */
class Reports
{
    /**
     * The reference's landing page, category by category, pill by pill.
     * key => [label, real?, why-disabled].
     *
     * @var array<string, array<string, array{0: string, 1: bool, 2?: string}>>
     */
    public const CATEGORIES = [
        'General' => [
            'daily-performance' => ['Daily Performance', true],
            'disk-usage' => ['Disk Usage Summary', false, 'Paymenter does not meter disk usage'],
            'monthly-orders' => ['Monthly Orders', true],
            'product-suspensions' => ['Product Suspensions', true],
            'promotions-usage' => ['Promotions Usage', true],
            'ssl-monitoring' => ['Ssl Certificate Monitoring', false, 'No certificate monitor is connected'],
        ],
        'Billing' => [
            'aging-invoices' => ['Aging Invoices', true],
            'credits-reviewer' => ['Credits Reviewer', true],
            'direct-debit' => ['Direct Debit Processing', false, 'No direct-debit gateway is connected'],
            'sales-tax' => ['Sales Tax Liability', false, 'Tax is not enabled on this store'],
            'vat-moss' => ['Vat Moss', false, 'Tax is not enabled on this store'],
        ],
        'Income' => [
            'annual-income' => ['Annual Income Report', true],
            'income-forecast' => ['Income Forecast', true],
            'income-by-product' => ['Income by Product', true],
            'monthly-transactions' => ['Monthly Transactions', true],
            'server-forecasts' => ['Server Revenue Forecasts', false, 'Services are not grouped by server'],
        ],
        'Clients' => [
            'new-customers' => ['New Customers', true],
            'client-sources' => ['Client Sources', false, 'Paymenter does not record signup sources'],
            'client-statement' => ['Client Statement', false, 'Open a client profile for their statement'],
            'clients-by-country' => ['Clients by Country', true],
            'top-clients' => ['Top 10 Clients by Income', true],
            'affiliates-overview' => ['Affiliates Overview', true],
            'domain-renewals' => ['Domain Renewal Emails', false, 'No domain registrar is connected'],
            'customer-retention' => ['Customer Retention Time', true],
        ],
        'Support' => [
            'ticket-replies' => ['Support Ticket Replies', true],
            'ticket-feedback' => ['Ticket Feedback Scores', true],
            'ticket-comments' => ['Ticket Feedback Comments', false, 'Paymenter tickets have no feedback'],
            'ticket-ratings' => ['Ticket Ratings Reviewer', false, 'Paymenter tickets have no ratings'],
            'ticket-tags' => ['Ticket Tags', false, 'Paymenter tickets have no tags'],
        ],
        'Exports' => [
            'export-clients' => ['Clients', true],
            'export-invoices' => ['Invoices', true],
            'export-services' => ['Services', true],
            'export-transactions' => ['Transactions', true],
            'export-domains' => ['Domains', false, 'No domain registrar is connected'],
            'export-pdf-batch' => ['Pdf Batch', false, 'Invoice PDFs are downloaded per invoice'],
        ],
        'System' => [
            'smarty' => ['Smarty Compatibility', false, 'Paymenter templates are Blade, not Smarty'],
        ],
    ];

    /** The chart palette, in the reference's own order of series. */
    public const COLORS = ['#337ab7', '#d9534f', '#f0ad4e', '#5cb85c', '#9b59b6', '#5bc0de'];

    /** @return array<string, string> Every real, non-export report — label => key, A→Z. */
    public static function railList(): array
    {
        $list = [];

        foreach (self::CATEGORIES as $category => $reports) {
            foreach ($reports as $key => $report) {
                if ($report[1] && $category !== 'Exports') {
                    $list[$report[0]] = $key;
                }
            }
        }

        ksort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    public static function label(string $key): ?string
    {
        foreach (self::CATEGORIES as $reports) {
            if (isset($reports[$key])) {
                return $reports[$key][0];
            }
        }

        return null;
    }

    /**
     * The report itself.
     *
     * @return array{title: string, description: string, chart: ?array, columns: array<int, string>, rows: array<int, array<int, string|int|float>>, note?: string}
     */
    public static function data(string $key): array
    {
        return match ($key) {
            'daily-performance' => self::dailyPerformance(),
            'monthly-orders' => self::monthlyOrders(),
            'product-suspensions' => self::productSuspensions(),
            'promotions-usage' => self::promotionsUsage(),
            'aging-invoices' => self::agingInvoices(),
            'credits-reviewer' => self::creditsReviewer(),
            'annual-income' => self::annualIncome(),
            'income-forecast' => self::incomeForecast(),
            'income-by-product' => self::incomeByProduct(),
            'monthly-transactions' => self::monthlyTransactions(),
            'new-customers' => self::newCustomers(),
            'clients-by-country' => self::clientsByCountry(),
            'top-clients' => self::topClients(),
            'affiliates-overview' => self::affiliatesOverview(),
            'customer-retention' => self::customerRetention(),
            'ticket-replies' => self::ticketReplies(),
            'ticket-feedback' => [
                'title' => 'Ticket Feedback Scores',
                'description' => 'Feedback scores for ticket handling.',
                'chart' => null,
                'columns' => ['Ticket', 'Score', 'Comment'],
                'rows' => [],
                'note' => 'No ratings are recorded — Paymenter tickets have no feedback scores.',
            ],
            default => abort(404),
        };
    }

    /** The reference's Daily Performance: a daily activity summary for the current month. */
    private static function dailyPerformance(): array
    {
        $start = now()->startOfMonth();
        $days = (int) $start->daysInMonth;
        $keys = ['orders', 'invoices', 'paid', 'tickets', 'replies', 'cancellations'];
        $byDay = array_fill(1, $days, array_fill_keys($keys, 0));

        $count = function ($query, string $slot, string $column = 'created_at') use (&$byDay, $start): void {
            foreach ($query->whereBetween($column, [$start, $start->copy()->endOfMonth()])->get([$column]) as $row) {
                $byDay[(int) Carbon::parse($row->{$column})->format('j')][$slot]++;
            }
        };

        $count(Order::query(), 'orders');
        $count(Invoice::query(), 'invoices');
        $count(Invoice::where('status', 'paid'), 'paid', 'updated_at');
        $count(Ticket::query(), 'tickets');
        $count(TicketMessage::query(), 'replies');
        if (class_exists(ServiceCancellation::class)) {
            $count(ServiceCancellation::query(), 'cancellations');
        }

        $series = [
            ['Completed Orders', 'orders'], ['New Invoices', 'invoices'], ['Paid Invoices', 'paid'],
            ['Opened Tickets', 'tickets'], ['Ticket Replies', 'replies'], ['Cancellation Requests', 'cancellations'],
        ];

        return [
            'title' => 'Daily Performance for ' . now()->format('F Y'),
            'description' => 'This report shows a daily activity summary for a given month.',
            'chart' => [
                'labels' => collect(range(1, $days))->map(fn ($d) => $start->copy()->day($d)->format('m/d/Y'))->all(),
                'series' => collect($series)->map(fn ($s, $i) => [
                    'label' => $s[0],
                    'color' => self::COLORS[$i],
                    'points' => array_values(array_map(fn ($day) => $day[$s[1]], $byDay)),
                ])->all(),
            ],
            'columns' => ['Date', 'Completed Orders', 'New Invoices', 'Paid Invoices', 'Opened Tickets', 'Ticket Replies', 'Cancellation Requests'],
            'rows' => collect(range(1, $days))->map(fn ($d) => [
                $start->copy()->day($d)->format('l m/d/Y'),
                $byDay[$d]['orders'], $byDay[$d]['invoices'], $byDay[$d]['paid'],
                $byDay[$d]['tickets'], $byDay[$d]['replies'], $byDay[$d]['cancellations'],
            ])->all(),
        ];
    }

    /** The reference's Income Forecast: projected renewals for the next 13 months. */
    private static function incomeForecast(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $cycles = ['Monthly' => [1, 'month'], 'Quarterly' => [3, 'month'], 'Semi-Annual' => [6, 'month'],
            'Annual' => [12, 'month'], 'Biennial' => [24, 'month'], 'Triennial' => [36, 'month']];
        $months = collect(range(0, 12))->map(fn ($i) => now()->startOfMonth()->addMonths($i));
        $grid = [];
        foreach ($months as $month) {
            $grid[$month->format('F Y')] = array_fill_keys(array_keys($cycles), 0.0);
        }

        $services = Service::where('status', 'active')->whereNotNull('expires_at')
            ->with('plan')->get();

        foreach ($services as $service) {
            $plan = $service->plan;
            if (!$plan) {
                continue;
            }

            $stepMonths = $plan->billing_unit === 'year'
                ? (int) $plan->billing_period * 12
                : (int) $plan->billing_period;

            $cycleName = array_search([$stepMonths ?: 1, 'month'], $cycles, true) ?: null;
            if (!$cycleName || $stepMonths < 1) {
                continue;
            }

            // Walk the renewals forward from the service's own due date.
            $due = Carbon::parse($service->expires_at)->startOfMonth();
            $horizon = $months->last();
            while ($due->lte($horizon)) {
                if ($due->gte($months->first())) {
                    $grid[$due->format('F Y')][$cycleName] += (float) $service->price * (float) $service->quantity;
                }
                $due = $due->addMonths($stepMonths);
            }
        }

        return [
            'title' => 'Income Forecast',
            'description' => 'This report shows the projected income for each month of the year if all active services are renewed within that month',
            'note' => 'Choose Currency: ' . $currency,
            'chart' => [
                'labels' => array_keys($grid),
                'series' => [[
                    'label' => 'Cumulative Income Forecast Total',
                    'color' => self::COLORS[0],
                    'points' => array_values(collect($grid)->reduce(function ($carry, $row) {
                        $carry[] = (end($carry) ?: 0) + array_sum($row);

                        return $carry;
                    }, [])),
                ]],
            ],
            'columns' => ['Month', ...array_keys($cycles), 'Total'],
            'rows' => collect($grid)->map(fn ($row, $month) => [
                $month,
                ...array_map(fn ($v) => '$' . number_format($v, 2) . ' ' . $currency, array_values($row)),
                '$' . number_format(array_sum($row), 2) . ' ' . $currency,
            ])->values()->all(),
        ];
    }

    private static function annualIncome(): array
    {
        $year = (int) now()->format('Y');
        $currency = config('settings.default_currency', 'USD');
        $rows = [];
        $points = [];

        foreach (range(1, 12) as $m) {
            $month = Carbon::create($year, $m, 1);
            $in = (float) InvoiceTransaction::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->sum('amount');
            $fees = (float) InvoiceTransaction::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->sum('fee');
            $rows[] = [$month->format('F Y'),
                '$' . number_format($in, 2) . ' ' . $currency,
                '$' . number_format($fees, 2) . ' ' . $currency,
                '$' . number_format($in - $fees, 2) . ' ' . $currency];
            $points[] = round($in, 2);
        }

        return [
            'title' => 'Annual Income Report',
            'description' => 'This report shows the income received per month for ' . $year . '.',
            'chart' => [
                'labels' => collect(range(1, 12))->map(fn ($m) => Carbon::create($year, $m, 1)->format('M'))->all(),
                'series' => [['label' => 'Income', 'color' => self::COLORS[0], 'points' => $points]],
            ],
            'columns' => ['Month', 'Income', 'Fees', 'Net Income'],
            'rows' => $rows,
        ];
    }

    private static function incomeByProduct(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $rows = Product::withCount('services')->get()
            ->map(function ($product) use ($currency) {
                $total = (float) Service::where('product_id', $product->id)->sum(\Illuminate\Support\Facades\DB::raw('price * quantity'));

                return [$product->name, $product->services_count, '$' . number_format($total, 2) . ' ' . $currency, $total];
            })
            ->sortByDesc(3)->values()->map(fn ($r) => array_slice($r, 0, 3))->all();

        return [
            'title' => 'Income by Product',
            'description' => 'This report shows the value of the services sold, product by product.',
            'chart' => null,
            'columns' => ['Product', 'Services', 'Total Value'],
            'rows' => $rows,
        ];
    }

    private static function monthlyTransactions(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $rows = [];

        foreach (range(0, 11) as $i) {
            $month = now()->startOfMonth()->subMonths(11 - $i);
            $q = InvoiceTransaction::whereBetween('created_at', [$month, $month->copy()->endOfMonth()]);
            $rows[] = [$month->format('F Y'), (clone $q)->count(),
                '$' . number_format((float) (clone $q)->sum('amount'), 2) . ' ' . $currency,
                '$' . number_format((float) (clone $q)->sum('fee'), 2) . ' ' . $currency];
        }

        return [
            'title' => 'Monthly Transactions',
            'description' => 'This report shows the transactions recorded per month for the last twelve months.',
            'chart' => null,
            'columns' => ['Month', 'Transactions', 'Amount In', 'Fees'],
            'rows' => $rows,
        ];
    }

    private static function monthlyOrders(): array
    {
        $rows = [];
        $points = [];

        foreach (range(0, 11) as $i) {
            $month = now()->startOfMonth()->subMonths(11 - $i);
            $count = Order::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count();
            $rows[] = [$month->format('F Y'), $count];
            $points[] = $count;
        }

        return [
            'title' => 'Monthly Orders',
            'description' => 'This report shows the orders placed per month for the last twelve months.',
            'chart' => [
                'labels' => collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths(11 - $i)->format('M y'))->all(),
                'series' => [['label' => 'Orders', 'color' => self::COLORS[0], 'points' => $points]],
            ],
            'columns' => ['Month', 'Orders Placed'],
            'rows' => $rows,
        ];
    }

    private static function productSuspensions(): array
    {
        $rows = Service::where('status', 'suspended')->with(['product', 'user'])->get()
            ->map(fn ($s) => [$s->id, $s->product?->name ?? '—',
                trim(($s->user->first_name ?? '') . ' ' . ($s->user->last_name ?? '')) ?: ($s->user->email ?? '—'),
                $s->expires_at?->format('m/d/Y') ?? '-'])->all();

        return [
            'title' => 'Product Suspensions',
            'description' => 'This report lists every currently suspended service.',
            'chart' => null,
            'columns' => ['Service ID', 'Product', 'Client', 'Due Date'],
            'rows' => $rows,
        ];
    }

    private static function promotionsUsage(): array
    {
        $rows = \App\Models\Coupon::query()->get()
            ->map(fn ($c) => [$c->code, Service::where('coupon_id', $c->id)->count()])->all();

        return [
            'title' => 'Promotions Usage',
            'description' => 'This report shows how many services each coupon has been used on.',
            'chart' => null,
            'columns' => ['Coupon Code', 'Services Using It'],
            'rows' => $rows,
        ];
    }

    private static function agingInvoices(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $buckets = ['Not yet due' => [null, 0], '1–30 days overdue' => [0, 30],
            '31–60 days overdue' => [30, 60], '61–90 days overdue' => [60, 90], 'Over 90 days overdue' => [90, null]];
        $rows = [];

        foreach ($buckets as $label => [$from, $to]) {
            $q = Invoice::where('status', 'pending')->whereNotNull('due_at');
            if ($from === null) {
                $q->where('due_at', '>', now());
            } else {
                $q->where('due_at', '<=', now()->subDays($from));
                if ($to !== null) {
                    $q->where('due_at', '>', now()->subDays($to));
                }
            }
            $invoices = $q->with('items')->get();
            $rows[] = [$label, $invoices->count(),
                '$' . number_format($invoices->sum(fn ($i) => (float) $i->total), 2) . ' ' . $currency];
        }

        return [
            'title' => 'Aging Invoices',
            'description' => 'This report shows the unpaid invoices by how long they have been owed.',
            'chart' => null,
            'columns' => ['Age', 'Invoices', 'Amount'],
            'rows' => $rows,
        ];
    }

    private static function creditsReviewer(): array
    {
        $rows = Credit::with('user')->orderByDesc('amount')->get()
            ->map(fn ($c) => [
                trim(($c->user->first_name ?? '') . ' ' . ($c->user->last_name ?? '')) ?: ($c->user->email ?? '—'),
                '$' . number_format((float) $c->amount, 2) . ' ' . $c->currency_code,
            ])->all();

        return [
            'title' => 'Credits Reviewer',
            'description' => 'This report shows every client holding account credit.',
            'chart' => null,
            'columns' => ['Client', 'Credit Balance'],
            'rows' => $rows,
        ];
    }

    private static function newCustomers(): array
    {
        $year = (int) now()->format('Y');
        $signups = [[], []];
        $rows = [];

        foreach (range(1, 12) as $m) {
            $month = Carbon::create($year, $m, 1);
            $last = $month->copy()->subYear();
            $new = User::whereNull('role_id')->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count();
            $prev = User::whereNull('role_id')->whereBetween('created_at', [$last, $last->copy()->endOfMonth()])->count();
            $placed = Order::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count();
            $completed = Order::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->whereHas('services', fn ($q) => $q->where('status', 'active'))->count();
            $signups[0][] = $new;
            $signups[1][] = $prev;
            $rows[] = [$month->format('F Y'), $new, $placed, $completed];
        }

        return [
            'title' => 'New Customers',
            'description' => 'This report shows the total number of new customers, orders and complete orders and compares each of these to the previous year on the graph.',
            'chart' => [
                'labels' => collect(range(1, 12))->map(fn ($m) => Carbon::create($year, $m, 1)->format('F'))->all(),
                'series' => [
                    ['label' => (string) $year, 'color' => self::COLORS[0], 'points' => $signups[0]],
                    ['label' => (string) ($year - 1), 'color' => '#b0b0b0', 'points' => $signups[1]],
                ],
                'heading' => 'New Signups',
            ],
            'columns' => ['Month', 'New Signups', 'Orders Placed', 'Orders Completed'],
            'rows' => $rows,
        ];
    }

    private static function clientsByCountry(): array
    {
        $rows = User::whereNull('role_id')->with(['properties' => fn ($q) => $q->where('key', 'country')])->get()
            ->groupBy(fn ($u) => $u->properties->first()?->value ?: '— Not set —')
            ->map(fn ($group, $country) => [$country, $group->count()])
            ->sortByDesc(1)->values()->all();

        return [
            'title' => 'Clients by Country',
            'description' => 'This report shows where the client base is.',
            'chart' => null,
            'columns' => ['Country', 'Clients'],
            'rows' => $rows,
        ];
    }

    private static function topClients(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $totals = InvoiceTransaction::with('invoice.user')->get()
            ->filter(fn ($t) => $t->invoice?->user)
            ->groupBy(fn ($t) => $t->invoice->user_id)
            ->map(fn ($group) => [
                'name' => trim(($group->first()->invoice->user->first_name ?? '') . ' ' . ($group->first()->invoice->user->last_name ?? ''))
                    ?: $group->first()->invoice->user->email,
                'total' => $group->sum(fn ($t) => (float) $t->amount),
            ])
            ->sortByDesc('total')->take(10)->values();

        return [
            'title' => 'Top 10 Clients by Income',
            'description' => 'This report shows the ten clients who have paid the most.',
            'chart' => null,
            'columns' => ['#', 'Client', 'Total Paid'],
            'rows' => $totals->map(fn ($row, $i) => [$i + 1, $row['name'],
                '$' . number_format($row['total'], 2) . ' ' . $currency])->all(),
        ];
    }

    private static function affiliatesOverview(): array
    {
        if (!class_exists(\Paymenter\Extensions\Others\Affiliates\Models\Affiliate::class)) {
            return ['title' => 'Affiliates Overview', 'description' => 'Affiliate performance.',
                'chart' => null, 'columns' => ['Affiliate', 'Visitors', 'Signups'], 'rows' => [],
                'note' => 'The Affiliates extension is not enabled.'];
        }

        $rows = \Paymenter\Extensions\Others\Affiliates\Models\Affiliate::with('user')->get()
            ->map(fn ($a) => [
                trim(($a->user->first_name ?? '') . ' ' . ($a->user->last_name ?? '')) ?: ($a->user->email ?? '—'),
                number_format($a->visitors), number_format($a->signups),
            ])->all();

        return [
            'title' => 'Affiliates Overview',
            'description' => 'This report shows every affiliate, their referred visitors and signups.',
            'chart' => null,
            'columns' => ['Affiliate', 'Visitors Referred', 'Signups'],
            'rows' => $rows,
        ];
    }

    private static function customerRetention(): array
    {
        $services = Service::whereIn('status', ['active', 'cancelled'])->get(['status', 'created_at', 'updated_at']);
        $months = $services->map(fn ($s) => Carbon::parse($s->created_at)
            ->diffInMonths($s->status === 'cancelled' ? Carbon::parse($s->updated_at) : now()));

        return [
            'title' => 'Customer Retention Time',
            'description' => 'This report shows how long services stay active before they are cancelled.',
            'chart' => null,
            'columns' => ['Measure', 'Value'],
            'rows' => [
                ['Services measured', $services->count()],
                ['Average lifetime', $months->count() ? round($months->avg(), 1) . ' months' : '—'],
                ['Longest lifetime', $months->count() ? (int) $months->max() . ' months' : '—'],
                ['Still active', $services->where('status', 'active')->count()],
            ],
        ];
    }

    private static function ticketReplies(): array
    {
        $rows = User::whereNotNull('role_id')->get()
            ->map(fn ($admin) => [
                trim($admin->first_name . ' ' . $admin->last_name) ?: $admin->email,
                TicketMessage::where('user_id', $admin->id)->count(),
                TicketMessage::where('user_id', $admin->id)->where('created_at', '>=', now()->subDays(30))->count(),
            ])->all();

        return [
            'title' => 'Support Ticket Replies',
            'description' => 'This report shows the replies written by each member of staff.',
            'chart' => null,
            'columns' => ['Staff Member', 'All Time', 'Last 30 Days'],
            'rows' => $rows,
        ];
    }
}
