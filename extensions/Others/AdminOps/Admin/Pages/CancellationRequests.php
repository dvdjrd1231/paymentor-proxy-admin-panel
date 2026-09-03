<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Service;
use App\Models\ServiceCancellation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\Cancellations\Support\Requests;

/**
 * Issue #30 — WHMCS's Cancellation Requests as the navy list: the Search/Filter band, the
 * Show Open/Completed Requests toggle, and the reference's columns. Accept/refuse are the
 * Cancellations extension's own {@see Requests} operations — the reasons this page exists
 * instead of core's Edit/Delete resource list (deleting a request is indistinguishable
 * from refusing it there).
 */
class CancellationRequests extends Page
{
    protected string $view = 'adminops::pages.cancellation-requests';

    protected static ?string $slug = 'service-cancellations';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'view')]
    public string $tab = 'open';

    public bool $filter = false;

    /** The one-box search the rail's Advanced Search still submits; the panel's own
     *  fields below are the reference's. */
    #[Url]
    public string $q = '';

    #[Url]
    public string $reason = '';

    #[Url]
    public string $client = '';

    /** Service ID — the reference's field, over the id the request actually carries. */
    #[Url]
    public string $svc = '';

    /** '' | immediate | end_of_period — the Type column's own two values. */
    #[Url]
    public string $type = '';

    public ?string $confirming = null;

    public string $confirmAction = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.services.viewAny');
    }

    public function getTitle(): string
    {
        return 'Cancellation Requests';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function confirm(int $id, string $action): void
    {
        $this->confirming = (string) $id;
        $this->confirmAction = $action;
    }

    public function run(): void
    {
        if (!$this->confirming || !class_exists(Requests::class)) {
            $this->reset(['confirming', 'confirmAction']);

            return;
        }

        $request = ServiceCancellation::with('service')->find((int) $this->confirming);
        $action = $this->confirmAction;
        $this->reset(['confirming', 'confirmAction']);

        if (!$request) {
            return;
        }

        if ($action === 'accept') {
            Requests::accept($request);
            Notification::make()->title('Cancellation accepted')
                ->body('The service is being terminated.')->success()->send();
        } elseif ($action === 'deny') {
            Requests::deny($request);
            Notification::make()->title('Request refused')
                ->body('The service goes back to renewing normally.')->success()->send();
        }
    }

    protected function getViewData(): array
    {
        $rows = ServiceCancellation::with(['service.product', 'service.user'])
            ->latest('id')->limit(300)->get()
            ->filter(fn (ServiceCancellation $row) => $row->service !== null)
            ->filter(fn (ServiceCancellation $row) => $this->tab === 'completed'
                ? $row->service->status === Service::STATUS_CANCELLED
                : $row->service->status !== Service::STATUS_CANCELLED)
            ->when(trim($this->q) !== '', function ($list) {
                $needle = strtolower(trim($this->q));

                return $list->filter(fn (ServiceCancellation $row) => str_contains(strtolower(
                    ($row->service->product?->name ?? '') . ' '
                    . ($row->service->user->first_name ?? '') . ' '
                    . ($row->service->user->last_name ?? '') . ' '
                    . ($row->service->user->email ?? '') . ' '
                    . ($row->reason ?? ''),
                ), $needle));
            })
            ->when(trim($this->reason) !== '', fn ($list) => $list->filter(
                fn (ServiceCancellation $row) => str_contains(strtolower($row->reason ?? ''), strtolower(trim($this->reason))),
            ))
            ->when(trim($this->client) !== '', function ($list) {
                $needle = strtolower(trim($this->client));

                return $list->filter(fn (ServiceCancellation $row) => str_contains(strtolower(
                    ($row->service->user->first_name ?? '') . ' '
                    . ($row->service->user->last_name ?? '') . ' '
                    . ($row->service->user->email ?? ''),
                ), $needle));
            })
            ->when(ctype_digit(trim($this->svc)), fn ($list) => $list->filter(
                fn (ServiceCancellation $row) => $row->service_id === (int) trim($this->svc),
            ))
            ->when($this->type !== '', fn ($list) => $list->filter(
                fn (ServiceCancellation $row) => $row->type === $this->type,
            ))
            ->values();

        return ['rows' => $rows];
    }
}
