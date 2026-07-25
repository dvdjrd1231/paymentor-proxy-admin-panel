<?php

namespace Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource;

class EditPaymentFeeRule extends EditRecord
{
    protected static string $resource = PaymentFeeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
