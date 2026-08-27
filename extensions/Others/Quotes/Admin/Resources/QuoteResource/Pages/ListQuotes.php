<?php

namespace Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource;

/** The list. Create and edit are modals — a quote is a subject, a date and its lines. */
class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create New Quote')];
    }
}
