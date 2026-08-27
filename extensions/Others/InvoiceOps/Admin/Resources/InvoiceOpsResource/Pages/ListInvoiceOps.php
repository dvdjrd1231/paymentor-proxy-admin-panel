<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\InvoiceOpsResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\InvoiceOpsResource;

/** The list and its three actions. Invoices are created and edited on core's own screens. */
class ListInvoiceOps extends ListRecords
{
    protected static string $resource = InvoiceOpsResource::class;
}
