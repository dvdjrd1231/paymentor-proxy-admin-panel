<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource;

class ListCannedResponses extends ListRecords
{
    protected static string $resource = CannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
