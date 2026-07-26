<?php

namespace Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource;

class ListGatewayRules extends ListRecords
{
    protected static string $resource = GatewayRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
