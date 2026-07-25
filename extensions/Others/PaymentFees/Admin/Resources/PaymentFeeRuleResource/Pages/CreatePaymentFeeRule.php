<?php

namespace Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource;

class CreatePaymentFeeRule extends CreateRecord
{
    protected static string $resource = PaymentFeeRuleResource::class;
}
