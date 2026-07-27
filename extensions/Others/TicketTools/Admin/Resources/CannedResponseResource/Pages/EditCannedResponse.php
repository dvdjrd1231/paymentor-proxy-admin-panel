<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource;

class EditCannedResponse extends EditRecord
{
    protected static string $resource = CannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
