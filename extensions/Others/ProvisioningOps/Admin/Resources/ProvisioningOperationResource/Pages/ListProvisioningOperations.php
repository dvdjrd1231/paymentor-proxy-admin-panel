<?php

namespace Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;

class ListProvisioningOperations extends ListRecords
{
    protected static string $resource = ProvisioningOperationResource::class;
}
