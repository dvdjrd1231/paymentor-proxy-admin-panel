<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Reports;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * One WHMCS report, to the screenshots: the report's own heading and description, the
 * Tools menu (Print, and Export CSV of the table below), the chart when the report has
 * one, and the striped table. Which report is the `{key}` in the path; the numbers all
 * come from {@see Reports}.
 */
class ReportView extends Page
{
    protected string $view = 'adminops::pages.report-view';

    protected static ?string $slug = 'report';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public string $reportKey = '';

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{key}';
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function mount(string $key): void
    {
        abort_if(Reports::label($key) === null, 404);
        $this->reportKey = $key;
    }

    public function getTitle(): string
    {
        return 'Reports';
    }

    /** Tools → Export CSV: this report's table, streamed. */
    public function exportCsv()
    {
        $data = Reports::data($this->reportKey);

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $data['columns']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, array_map(fn ($v) => (string) $v, $row));
            }
            fclose($out);
        }, $this->reportKey . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    protected function getViewData(): array
    {
        return ['report' => Reports::data($this->reportKey)];
    }
}
