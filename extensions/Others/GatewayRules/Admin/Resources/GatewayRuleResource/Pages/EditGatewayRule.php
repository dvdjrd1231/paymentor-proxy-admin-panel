<?php

namespace Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource;

class EditGatewayRule extends EditRecord
{
    protected static string $resource = GatewayRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
