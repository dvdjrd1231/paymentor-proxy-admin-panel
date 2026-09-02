<?php

namespace Paymenter\Extensions\Others\Announcements\Admin\Resources\AnnouncementResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AnnouncementsAdmin;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\Announcements\Admin\Resources\AnnouncementResource;

class ListAnnouncements extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = AnnouncementResource::class;

    protected static function whmcsPageUrl(): string
    {
        return AnnouncementsAdmin::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
