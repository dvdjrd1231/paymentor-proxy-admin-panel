<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;

/**
 * Admin URLs that belong to another extension.
 *
 * Only one so far: the provisioning operations list is owned by `Others/ProvisioningOps`,
 * which may not be installed. The `use` above is safe either way — an import is a
 * compile-time alias and loads nothing — so `class_exists()` is what actually decides, and
 * AdminOps keeps working on its own.
 */
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
