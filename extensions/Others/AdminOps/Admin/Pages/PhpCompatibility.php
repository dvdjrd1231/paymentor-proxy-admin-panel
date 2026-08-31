<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's PHP Version Compatibility: is this PHP what the platform wants, extension by
 * extension — checked against Paymenter's actual composer requirements.
 */
class PhpCompatibility extends Page
{
    protected string $view = 'adminops::pages.php-compatibility';

    protected static ?string $slug = 'php-compatibility';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'PHP Version Compatibility';
    }

    protected function getViewData(): array
    {
        $required = '8.3';

        $checks = [
            ['PHP ' . $required . ' or newer', PHP_VERSION, version_compare(PHP_VERSION, $required, '>=')],
        ];

        foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip'] as $extension) {
            $checks[] = ['Extension: ' . $extension, extension_loaded($extension) ? 'Loaded' : 'Missing', extension_loaded($extension)];
        }

        return [
            'checks' => $checks,
            'allGood' => !in_array(false, array_column($checks, 2), true),
        ];
    }
}
