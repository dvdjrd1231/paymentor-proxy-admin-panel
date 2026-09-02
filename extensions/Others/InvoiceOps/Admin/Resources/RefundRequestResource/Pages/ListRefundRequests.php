<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\RefundRequests;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource;

/** Was the queue. {@see RefundRequests} (the reference's "Disputes") replaced it. */
class ListRefundRequests extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = RefundRequestResource::class;

    protected static function whmcsPageUrl(): string
    {
        return RefundRequests::getUrl();
    }
}
