<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

/**
 * A native Filament list page that a WHMCS-styled AdminOps page has fully replaced.
 *
 * Excluding these from the menus (see WhmcsNavigation) stops a click from ever landing here
 * — but the route itself is still real, and a bookmark, browser autocomplete, or a link
 * saved before the replacement existed can still walk straight to it. This is the other
 * half: the page redirects itself to whatever now owns the feature, so even that direct
 * visit lands on the current one.
 *
 * Applied to the `List…` page of an extension's own Resource, one `use` and one constant —
 * never to a core resource, which this project does not edit.
 */
trait RedirectsToWhmcsPage
{
    public function mount(): void
    {
        $this->redirect(static::whmcsPageUrl());
    }

    /** The AdminOps page that replaced this list — a static call, resolved lazily. */
    abstract protected static function whmcsPageUrl(): string;
}
