<?php

namespace Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\BillableItemsList;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource;

/**
 * Was the list, with create and edit in modals. {@see BillableItemsList} replaced it.
 */
class ListBillableItems extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = BillableItemResource::class;

    protected static function whmcsPageUrl(): string
    {
        return BillableItemsList::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add New')];
    }
}
