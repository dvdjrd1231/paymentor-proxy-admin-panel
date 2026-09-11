<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;

/** Admin URLs that belong to another extension. */
class Links
{
    /**
     * The ProvisioningOps failure list, or null when that extension is not installed.
     */
    public static function provisioning(): ?string
    {
        if (!class_exists(ProvisioningOperationResource::class)) {
            return null;
        }

        return ProvisioningOperationResource::getUrl('index');
    }
}
