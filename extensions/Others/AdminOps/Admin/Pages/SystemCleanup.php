<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's System Cleanup, with the things this stack actually accumulates: compiled view
 * and application caches, and the operational logs that grow forever — debug, email and
 * HTTP — pruned past a sensible age. Every button says what it will remove before it does.
 */
class SystemCleanup extends Page
{
    protected string $view = 'adminops::pages.system-cleanup';

    protected static ?string $slug = 'system-cleanup';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'System Cleanup';
    }

    public function clearViews(): void
    {
        Artisan::call('view:clear');
        Notification::make()->title('Compiled views cleared')->success()->send();
    }

    public function clearCache(): void
    {
        Artisan::call('cache:clear');
        Notification::make()->title('Application cache cleared')->success()->send();
    }

    public function pruneLog(string $table): void
    {
        [$column, $days] = self::LOGS[$table] ?? [null, null];

        if (!$column || !Schema::hasTable($table)) {
            Notification::make()->title('Nothing to prune')->warning()->send();

            return;
        }

        $deleted = DB::table($table)->where($column, '<', now()->subDays($days))->delete();
        Notification::make()->title(number_format($deleted) . ' rows pruned from ' . $table)->success()->send();
    }

    /** table => [timestamp column, keep-days]. */
    public const LOGS = [
        'debug_logs' => ['created_at', 30],
        'email_logs' => ['created_at', 90],
        'http_logs' => ['created_at', 30],
        'audits' => ['created_at', 180],
    ];

    protected function getViewData(): array
    {
        $rows = [];

        foreach (self::LOGS as $table => [$column, $days]) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $rows[] = [
                'table' => $table,
                'days' => $days,
                'total' => DB::table($table)->count(),
                'stale' => DB::table($table)->where($column, '<', now()->subDays($days))->count(),
            ];
        }

        return ['logs' => $rows];
    }
}
