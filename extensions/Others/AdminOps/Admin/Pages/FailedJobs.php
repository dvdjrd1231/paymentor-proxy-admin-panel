<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\FailedJobResource;
use App\Models\FailedJob;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/** The queue's failures on the window standard, each row unfolding its exception. */
class FailedJobs extends Page
{
    protected string $view = 'adminops::pages.failed-jobs';

    protected static ?string $slug = 'queue-failures';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 50;

    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $queue = '';

    #[Url]
    public string $dates = '';

    #[Url]
    public int $page = 1;

    public ?int $expanded = null;

    public static function canAccess(): bool
    {
        return FailedJobResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Failed Jobs';
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

    public function retryJob(int $id): void
    {
        $job = FailedJob::findOrFail($id);

        abort_unless(FailedJobResource::canEdit($job), 403);

        $job->retry();

        Notification::make()->title('Job queued for retry')->success()->send();
    }

    public function deleteJob(int $id): void
    {
        $job = FailedJob::findOrFail($id);

        abort_unless(FailedJobResource::canDelete($job), 403);

        $job->delete();
        $this->expanded = null;

        Notification::make()->title('Failed job deleted')->success()->send();
    }

    public function retryAll(): void
    {
        abort_unless(FailedJobResource::canCreate() || FailedJobResource::canViewAny(), 403);

        $count = 0;

        foreach (FailedJob::query()->cursor() as $job) {
            $job->retry();
            $count++;
        }

        Notification::make()->title($count ? 'Queued ' . $count . ' job(s) for retry' : 'Nothing to retry')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /** The job's class name, which is what the row is really about. */
    public static function jobName(FailedJob $job): string
    {
        $payload = json_decode((string) $job->payload, true);

        return $payload['displayName'] ?? ($payload['job'] ?? '—');
    }

    /** The exception's first line — the rest is in the unfolded row. */
    public static function reason(FailedJob $job): string
    {
        return trim(strtok((string) $job->exception, "\n")) ?: '—';
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->range();

        return [
            'rows' => FailedJob::query()
                ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('payload', 'like', '%' . $this->q . '%')
                    ->orWhere('exception', 'like', '%' . $this->q . '%')))
                ->when($this->queue !== '', fn ($query) => $query->where('queue', $this->queue))
                ->when($from, fn ($query) => $query->whereDate('failed_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('failed_at', '<=', $to))
                ->latest('id')
                ->paginate(self::PER_PAGE, page: $this->page),
            'queues' => FailedJob::query()->distinct()->orderBy('queue')->pluck('queue')->filter()->all(),
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
