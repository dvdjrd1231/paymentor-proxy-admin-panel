<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\DebugLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #17 — WHMCS's Gateway Log as the navy list. Paymenter's nearest record is the
 * debug log of outbound HTTP calls, gateways included; each row unfolds its full context.
 */
class HttpLogs extends Page
{
    protected string $view = 'adminops::pages.http-logs';

    protected static ?string $slug = 'gateway-log';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    #[Url]
    public string $q = '';

    #[Url]
    public int $page = 1;

    public ?int $expanded = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Gateway Log';
    }

    public function expand(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    protected function getViewData(): array
    {
        return [
            'rows' => DebugLog::query()
                ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('type', 'like', '%' . $this->q . '%')
                    ->orWhere('context', 'like', '%' . $this->q . '%')))
                ->latest('id')
                ->paginate(self::PER_PAGE, page: $this->page),
        ];
    }
}
