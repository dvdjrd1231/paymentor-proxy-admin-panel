<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Service;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Reports;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Reports landing page, to its screenshot: the intro line, then the category
 * sections — General, Billing, Income, Clients, Support, Exports, System — each a row of
 * pill buttons. Real reports open {@see ReportView}; the Exports pills download real CSVs
 * of the store's data; pills with no backend are disabled and say why.
 */
class ReportsHome extends Page
{
    protected string $view = 'adminops::pages.reports-home';

    protected static ?string $slug = 'reports';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Reports';
    }

    /** The Exports pills: the dataset as CSV, streamed — nothing written server-side. */
    public function export(string $what)
    {
        $rows = match ($what) {
            'clients' => User::whereNull('role_id')->with('properties')->get()->map(fn ($u) => [
                $u->id, $u->first_name, $u->last_name, $u->email,
                $u->properties->firstWhere('key', 'company_name')?->value,
                $u->properties->firstWhere('key', 'country')?->value,
                $u->created_at,
            ])->prepend(['ID', 'First Name', 'Last Name', 'Email', 'Company', 'Country', 'Created']),
            'invoices' => Invoice::with('items')->get()->map(fn ($i) => [
                $i->id, $i->number, $i->user_id, $i->status, $i->total, $i->currency_code, $i->due_at, $i->created_at,
            ])->prepend(['ID', 'Number', 'User ID', 'Status', 'Total', 'Currency', 'Due', 'Created']),
            'services' => Service::with('product')->get()->map(fn ($s) => [
                $s->id, $s->user_id, $s->product?->name, $s->status, $s->price, $s->quantity, $s->currency_code, $s->expires_at,
            ])->prepend(['ID', 'User ID', 'Product', 'Status', 'Price', 'Quantity', 'Currency', 'Expires']),
            'transactions' => InvoiceTransaction::with('gateway')->get()->map(fn ($t) => [
                $t->id, $t->invoice_id, $t->gateway?->name, $t->amount, $t->fee, $t->transaction_id, $t->created_at,
            ])->prepend(['ID', 'Invoice ID', 'Gateway', 'Amount', 'Fee', 'Transaction ID', 'Created']),
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => (string) $v, (array) $row));
            }
            fclose($out);
        }, $what . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    protected function getViewData(): array
    {
        return ['categories' => Reports::CATEGORIES];
    }
}
