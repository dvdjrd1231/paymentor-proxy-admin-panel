<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Database Status, to its screenshot: every table with its rows and size, split
 * across two columns, with Optimise Tables doing the real thing. The backup button points
 * at the truth — backups here are `scripts/backup`, run on the host on a schedule, not a
 * PHP request that would time out half-written.
 */
class DatabaseStatus extends Page
{
    protected string $view = 'adminops::pages.database-status';

    protected static ?string $slug = 'database-status';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'Database Status';
    }

    /** The reference's Optimise Tables: OPTIMIZE TABLE, table by table. */
    public function optimise(): void
    {
        $count = 0;

        foreach (self::tables() as $table) {
            try {
                DB::statement('OPTIMIZE TABLE `' . $table->name . '`');
                $count++;
            } catch (\Throwable $e) {
                // InnoDB answers OPTIMIZE with a note and a rebuild; a table that refuses
                // is skipped, not fatal.
            }
        }

        Notification::make()->title($count . ' tables optimised')->success()->send();
    }

    /** @return array<int, object{name: string, row_count: int, size: int}> */
    private static function tables(): array
    {
        return DB::select(
            'select table_name as name, table_rows as row_count,
                    (data_length + index_length) as size
             from information_schema.tables
             where table_schema = database()
             order by table_name'
        );
    }

    protected function getViewData(): array
    {
        $tables = self::tables();
        $half = (int) ceil(count($tables) / 2);

        return [
            'columns' => [array_slice($tables, 0, $half), array_slice($tables, $half)],
            'totalSize' => array_sum(array_map(fn ($t) => (int) $t->size, $tables)),
            'count' => count($tables),
        ];
    }
}
