<?php

namespace Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\QuotesList;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource;

/** Was the list, with create and edit in modals. {@see QuotesList} replaced it. */
class ListQuotes extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = QuoteResource::class;

    protected static function whmcsPageUrl(): string
    {
        return QuotesList::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create New Quote')];
    }
}
