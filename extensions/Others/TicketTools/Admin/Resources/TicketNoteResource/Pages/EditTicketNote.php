<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource;

class EditTicketNote extends EditRecord
{
    protected static string $resource = TicketNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
