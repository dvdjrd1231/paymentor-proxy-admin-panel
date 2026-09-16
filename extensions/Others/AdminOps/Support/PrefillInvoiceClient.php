<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Resources\InvoiceResource\Pages\CreateInvoice;
use Livewire\ComponentHook;

/**
 * Core's Create Invoice arrives with the User box empty, so a client profile's "Create
 * Invoice" lost the client you started from and staff had to pick them again (Leandro,
 * issue #53).
 *
 * The page itself cannot be told: `CreateRecord::fillForm()` calls `$this->form->fill()`
 * with no arguments, and that file is vendored core. A Livewire component hook reaches the
 * same component from outside, which leaves core untouched and — the point of doing it
 * this way rather than building our own screen — leaves the page looking exactly as it
 * does today.
 *
 * Filled on mount, after fillForm() has run: Livewire's own lifecycle hook is registered
 * before this one and is what calls the component's mount(), so by the time this runs the
 * empty form state is already in place and ours is the last word.
 */
class PrefillInvoiceClient extends ComponentHook
{
    public function mount($params, $parent = null, $attributes = null): void
    {
        if (!$this->component instanceof CreateInvoice) {
            return;
        }

        // `for` rides on the initial page load only; a Livewire update posts to its own
        // endpoint without it, so a box the admin has deliberately cleared stays cleared.
        $for = (int) request()->query('for');

        if (!$for || !empty($this->component->data['user_id'])) {
            return;
        }

        $this->component->data['user_id'] = $for;
    }
}
