<?php

namespace Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource;

class ListAffiliates extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = AffiliateResource::class;

    protected static function whmcsPageUrl(): string
    {
        return ManageAffiliates::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
