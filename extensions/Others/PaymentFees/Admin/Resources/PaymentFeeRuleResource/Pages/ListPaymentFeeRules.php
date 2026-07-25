<?php

namespace Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource;

class ListPaymentFeeRules extends ListRecords
{
    protected static string $resource = PaymentFeeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
