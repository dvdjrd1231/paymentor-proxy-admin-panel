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

    /** The reference's Search/Filter panel: Date Range, Debug Data, Gateway, Result. */
    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $dates = '';

    #[Url]
    public string $gateway = '';

    #[Url]
    public int $page = 1;

    public ?int $expanded = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        // The reference's own heading; the sidebar keeps calling it Gateway Log.
        return 'Gateway Transaction Log';
    }

    public function mount(): void
    {
        // The reference arrives with the last three months pre-filled.
        if ($this->dates === '') {
            $this->dates = now()->subMonths(3)->format('m/d/Y') . ' - ' . now()->format('m/d/Y');
        }
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
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
        [$from, $to] = $this->range();

        return [
            'rows' => DebugLog::query()
                ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('type', 'like', '%' . $this->q . '%')
                    ->orWhere('context', 'like', '%' . $this->q . '%')))
                ->when($this->gateway !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('type', 'like', '%' . $this->gateway . '%')
                    ->orWhere('context', 'like', '%' . $this->gateway . '%')))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id')
                ->paginate(self::PER_PAGE, page: $this->page),
            'gateways' => \App\Models\Gateway::orderBy('name')->pluck('name')->all(),
        ];
    }

    /** Which gateway a row belongs to — its name, found in the type or the payload. */
    public static function gatewayOf($row, array $gateways): string
    {
        $haystack = strtolower($row->type . ' ' . json_encode($row->context));

        foreach ($gateways as $name) {
            if (str_contains($haystack, strtolower($name))) {
                return $name;
            }
        }

        return '—';
    }

    /** @return array{?string, ?string} */
    private function range(): array
    {
        $parse = function (string $piece): ?string {
            foreach (['m/d/Y', 'Y-m-d'] as $format) {
                try {
                    return \Carbon\Carbon::createFromFormat($format, trim($piece))->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }

            return null;
        };

        $pieces = preg_split('/\s+[-–]\s+/', trim($this->dates), 2);
        $from = $parse($pieces[0] ?? '');
        $to = isset($pieces[1]) ? $parse($pieces[1]) : $from;

        return [$from, $to];
    }
}
