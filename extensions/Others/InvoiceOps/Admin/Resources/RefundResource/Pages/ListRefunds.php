<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundResource;

/** The ledger. Refunds are recorded from an invoice, never typed in here. */
class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;
}
