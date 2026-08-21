<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * My Quotes.
 *
 * Paymenter has no quoting system: an invoice is only ever `pending`, `paid` or
 * `cancelled`, and there is no draft/proposal state that could stand in for a quote
 * without misrepresenting a real invoice as one. The page therefore renders the
 * reference portal's empty state rather than inventing data — which is also what the
 * reference itself shows for this account (its QUOTES counter reads 0).
 *
 * The list is built here rather than inlined in the view so that a future quoting
 * extension only has to fill this collection.
 */
class Quotes extends Component
{
    public function render()
    {
        $quotes = new Collection();

        return view('clienttools::quotes', ['quotes' => $quotes]);
    }
}
