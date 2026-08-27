<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource;

/** The queue. Requests are raised by customers, never typed in here. */
class ListRefundRequests extends ListRecords
{
    protected static string $resource = RefundRequestResource::class;
}
