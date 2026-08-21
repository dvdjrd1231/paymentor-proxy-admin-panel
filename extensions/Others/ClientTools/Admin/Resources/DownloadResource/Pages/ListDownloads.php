<?php

namespace Paymenter\Extensions\Others\ClientTools\Admin\Resources\DownloadResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\ClientTools\Admin\Resources\DownloadResource;

class ListDownloads extends ListRecords
{
    protected static string $resource = DownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
