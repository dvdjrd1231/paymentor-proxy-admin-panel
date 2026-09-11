<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ErrorLogResource;
use App\Models\DebugLog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The Error Log on the window standard. Same rows core's resource lists — DebugLog of
 * type `exception` — with the message, file and line lifted out of the context payload.
 */
class ErrorLog extends Page
{
    protected string $view = 'adminops::pages.error-log';

    protected static ?string $slug = 'error-log';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 50;

    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $dates = '';

    #[Url]
    public int $page = 1;

    public ?int $expanded = null;

    public bool $confirmingClear = false;

    public static function canAccess(): bool
    {
        return ErrorLogResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Error Log';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function expand(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function clearLog(): void
    {
        abort_unless(ErrorLogResource::canDeleteAny(), 403);

        $count = DebugLog::where('type', 'exception')->delete();
        $this->confirmingClear = false;
        $this->expanded = null;

        Notification::make()->title($count ? 'Cleared ' . number_format($count) . ' entries' : 'The log was already empty')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /** @return array<string, mixed> */
    public static function context(DebugLog $row): array
    {
        return is_array($row->context) ? $row->context : (array) json_decode((string) $row->context, true);
    }

    public static function field(DebugLog $row, string $key): string
    {
        return (string) (static::context($row)[$key] ?? '');
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->range();

        return [
            'rows' => DebugLog::query()
                ->where('type', 'exception')
                ->when($this->q !== '', fn ($query) => $query->where('context', 'like', '%' . $this->q . '%'))
                ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
                ->latest('id')
                ->paginate(self::PER_PAGE, page: $this->page),
            'canClear' => ErrorLogResource::canDeleteAny(),
        ];
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
