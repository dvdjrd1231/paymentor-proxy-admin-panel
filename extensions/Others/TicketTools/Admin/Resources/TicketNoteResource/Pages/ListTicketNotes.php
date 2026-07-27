<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource;

class ListTicketNotes extends ListRecords
{
    protected static string $resource = TicketNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
