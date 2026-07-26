<?php

namespace Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource;

class CreateGatewayRule extends CreateRecord
{
    protected static string $resource = GatewayRuleResource::class;
}
