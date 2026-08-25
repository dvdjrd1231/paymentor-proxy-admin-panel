<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use Filament\Widgets\Widget;

/**
 * WHMCS's homepage shortcuts: the few things staff start a session by creating.
 *
 * Everything here is reachable from the sidebar in two or three clicks; the point is the
 * first click. Each entry is hidden unless the signed-in administrator may actually create
 * that record, so a support-only role is not shown a button that would refuse them.
 *
 * @link docs/02b-admin-area.md
 */
class Shortcuts extends Widget
{
    protected string $view = 'adminops::widgets.shortcuts';

    protected int|string|array $columnSpan = 'full';

    /** First thing on the dashboard — it is the smallest and the most used. */
    protected static ?int $sort = -4;

    protected function getViewData(): array
    {
        $shortcuts = [];

        if (UserResource::canCreate()) {
            $shortcuts[] = ['label' => 'New customer', 'icon' => 'heroicon-m-user-plus', 'url' => UserResource::getUrl('create')];
        }

        if (ServiceResource::canCreate()) {
            $shortcuts[] = ['label' => 'New service', 'icon' => 'heroicon-m-server-stack', 'url' => ServiceResource::getUrl('create')];
        }

        if (InvoiceResource::canCreate()) {
            $shortcuts[] = ['label' => 'New invoice', 'icon' => 'heroicon-m-document-plus', 'url' => InvoiceResource::getUrl('create')];
        }

        if (TicketResource::canCreate()) {
            $shortcuts[] = ['label' => 'Open ticket', 'icon' => 'heroicon-m-lifebuoy', 'url' => TicketResource::getUrl('create')];
        }

        if (UserResource::canViewAny()) {
            $shortcuts[] = ['label' => 'Find a customer', 'icon' => 'heroicon-m-magnifying-glass', 'url' => UserResource::getUrl('index')];
        }

        return ['shortcuts' => $shortcuts];
    }

    public static function canView(): bool
    {
        return UserResource::canViewAny();
    }
}
