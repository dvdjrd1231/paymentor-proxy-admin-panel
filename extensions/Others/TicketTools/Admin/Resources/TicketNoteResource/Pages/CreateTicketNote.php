<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource;

class CreateTicketNote extends CreateRecord
{
    protected static string $resource = TicketNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}
