<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PredefinedReplies;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource;

/** Was the list. {@see PredefinedReplies} reads and writes the same CannedResponse rows. */
class ListCannedResponses extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = CannedResponseResource::class;

    protected static function whmcsPageUrl(): string
    {
        return PredefinedReplies::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
