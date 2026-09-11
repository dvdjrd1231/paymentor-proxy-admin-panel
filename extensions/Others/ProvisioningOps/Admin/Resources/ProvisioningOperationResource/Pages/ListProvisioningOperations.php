<?php

namespace Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;
use Paymenter\Extensions\Others\ProvisioningOps\Models\ProvisioningOperation;

/**
 * The Module Queue in the panel's standard window (Leandro, 2026-09-09: "the current
 * window differs from the standard used for new windows").
 */
class ListProvisioningOperations extends Page
{
    protected static string $resource = ProvisioningOperationResource::class;

    protected string $view = 'provisioningops::pages.provisioning-operations';

    /** The Search/Filter band starts closed, as the reference's does. */
    public bool $filter = false;

    public string $q = '';

    public string $status = '';

    public string $action = '';

    /** The row awaiting its "Are you sure?", or null. */
    public ?int $confirming = null;

    public function getTitle(): string
    {
        return 'Module Queue';
    }

    /**
     * A resource page draws the resource's breadcrumbs, which on a single-page resource
     * read "Module Queue › Module Queue" above a heading that says it a third time. The
     * standard windows elsewhere carry the title alone.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Retry is destructive in the sense that matters here — it calls a live panel API —
     * so it asks first, the way the rest of the admin does.
     */
    public function confirm(int $id): void
    {
        $this->confirming = $id;
    }

    public function runRetry(): void
    {
        $row = ProvisioningOperation::find($this->confirming);
        $this->confirming = null;

        if (! $row) {
            Notification::make()->title('That operation is no longer in the queue')->danger()->send();

            return;
        }

        ProvisioningOperationResource::retryOperation($row);
    }

    public function deleteRow(int $id): void
    {
        ProvisioningOperation::where('id', $id)->delete();

        Notification::make()->title('Removed from the queue')->success()->send();
    }

    /** @return Collection<int, ProvisioningOperation> */
    private function rows(): Collection
    {
        return ProvisioningOperation::with('service.user')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
            ->when(trim($this->q) !== '', function ($query): void {
                $term = '%' . trim($this->q) . '%';

                $query->where(function ($inner) use ($term): void {
                    $inner->where('extension', 'like', $term)
                        ->orWhere('error', 'like', $term)
                        ->orWhereHas('service.user', fn ($user) => $user->where('email', 'like', $term));
                });
            })
            ->orderByDesc('last_attempt_at')
            ->limit(200)
            ->get();
    }

    protected function getViewData(): array
    {
        return [
            'rows' => $this->rows(),
            'actions' => [
                'create' => 'Create',
                'suspend' => 'Suspend',
                'unsuspend' => 'Unsuspend',
                'terminate' => 'Terminate',
                'upgrade' => 'Upgrade',
                'callback' => 'Callback',
            ],
        ];
    }
}
