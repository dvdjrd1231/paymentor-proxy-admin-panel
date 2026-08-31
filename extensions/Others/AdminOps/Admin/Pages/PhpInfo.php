<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's PHP Info — the settings that matter, as a table. Deliberately not raw
 * `phpinfo()`: that dump includes the environment, and the environment includes the
 * database password. The curated rows answer every question staff actually ask of it.
 */
class PhpInfo extends Page
{
    protected string $view = 'adminops::pages.php-info';

    protected static ?string $slug = 'php-info';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'PHP Info';
    }

    protected function getViewData(): array
    {
        return [
            'facts' => [
                'PHP Version' => PHP_VERSION,
                'Interface' => php_sapi_name(),
                'Operating System' => php_uname('s') . ' ' . php_uname('r'),
                'Memory Limit' => ini_get('memory_limit'),
                'Max Execution Time' => ini_get('max_execution_time') . 's',
                'Upload Max Filesize' => ini_get('upload_max_filesize'),
                'Post Max Size' => ini_get('post_max_size'),
                'Max Input Vars' => ini_get('max_input_vars'),
                'OPcache' => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'Enabled' : 'Disabled',
                'Timezone' => ini_get('date.timezone') ?: date_default_timezone_get(),
                'Error Reporting Display' => ini_get('display_errors') ? 'On' : 'Off',
            ],
            'extensions' => collect(get_loaded_extensions())->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];
    }
}
