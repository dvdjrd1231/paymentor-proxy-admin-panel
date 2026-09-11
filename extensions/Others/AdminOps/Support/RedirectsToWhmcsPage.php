<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

/** A native Filament list page that a WHMCS-styled AdminOps page has fully replaced. */
trait RedirectsToWhmcsPage
{
    public function mount(): void
    {
        $this->redirect(static::whmcsPageUrl());
    }

    /** The AdminOps page that replaced this list — a static call, resolved lazily. */
    abstract protected static function whmcsPageUrl(): string;
}
