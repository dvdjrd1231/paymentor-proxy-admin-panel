<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Resources\InvoiceResource\Pages\CreateInvoice;

use function Livewire\on;

/**
 * Core's Create Invoice arrives with the User box empty, so a client profile's "Create
 * Invoice" lost the client you started from and staff had to pick them again (Leandro,
 * issue #53).
 *
 * The page cannot be told directly: `CreateRecord::fillForm()` calls `$this->form->fill()`
 * with no arguments, and that file is vendored core. This listens to the same component
 * from outside, which leaves core untouched and — the point of doing it this way rather
 * than building our own screen — leaves the page looking exactly as it does today.
 */
class PrefillInvoiceClient
{
    public static function register(): void
    {
        // A plain listener, not a Livewire ComponentHook: hooks are wired once when
        // Livewire boots, which is before extensions boot, so one registered here would
        // sit in the registry and never be called. Listeners added now simply run after
        // the ones already there — which is exactly what this needs, since the built-in
        // lifecycle listener is what calls the component's own mount() and fills the form.
        on('mount', function ($component, $params, $key, $parent, $attributes = null): void {
            if (!$component instanceof CreateInvoice) {
                return;
            }

            // `for` rides on the initial page load only; a Livewire update posts to its
            // own endpoint without it, so a box the admin has cleared stays cleared.
            $for = (int) request()->query('for');

            if (!$for || !empty($component->data['user_id'])) {
                return;
            }

            $component->data['user_id'] = $for;
        });
    }
}
