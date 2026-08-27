<?php

namespace Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource;

/**
 * The list, with create and edit in modals rather than on pages of their own.
 *
 * A billable item is eight fields and is usually written while looking at the list of what
 * else is waiting for that customer — a full page would lose that context for no gain.
 */
class ListBillableItems extends ListRecords
{
    protected static string $resource = BillableItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add New')];
    }
}
