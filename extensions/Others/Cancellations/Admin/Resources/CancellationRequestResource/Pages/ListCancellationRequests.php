<?php

namespace Paymenter\Extensions\Others\Cancellations\Admin\Resources\CancellationRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\CancellationRequests;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\Cancellations\Admin\Resources\CancellationRequestResource;

/** The list and its two actions. A request is raised by a customer, never typed in here. */
class ListCancellationRequests extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = CancellationRequestResource::class;

    protected static function whmcsPageUrl(): string
    {
        return CancellationRequests::getUrl();
    }
}
