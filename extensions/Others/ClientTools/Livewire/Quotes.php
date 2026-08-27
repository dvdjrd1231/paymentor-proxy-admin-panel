<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Paymenter\Extensions\Others\Quotes\Models\Quote;

/**
 * My Quotes.
 *
 * This shipped as an empty state, with a note saying a future quoting extension would only
 * have to fill the collection. `Others/Quotes` is that extension, and this now reads it.
 *
 * The dependency runs one way and softly: if Quotes is not installed the table is absent, the
 * collection is empty, and the page renders exactly the empty state it always did. ClientTools
 * does not require the other extension and never fails because of it.
 */
class Quotes extends Component
{
    public function render()
    {
        return view('clienttools::quotes', ['quotes' => $this->quotes()]);
    }

    /**
     * The customer's own quotes, newest first — drafts excluded.
     *
     * A draft is a quote still being written. Showing one would be the invoice-draft problem
     * again: the customer sees a price nobody has agreed to send them.
     *
     * @return Collection<int, Quote>
     */
    private function quotes(): Collection
    {
        if (!$this->available()) {
            return new Collection;
        }

        return Quote::query()
            ->where('user_id', Auth::id())
            ->where('status', '!=', Quote::STATUS_DRAFT)
            ->with('items')
            ->latest('id')
            ->get();
    }

    /**
     * The customer accepts — which raises a real invoice for the full amount.
     *
     * Ownership is checked here and not only in the query, because this takes an id from the
     * browser: without it, anyone signed in could accept somebody else's quote and create a
     * debt on their account. The guard inside {@see Quoting::accept()} handles the rest —
     * two tabs, or two presses, produce one invoice.
     */
    public function accept(int $id)
    {
        $quote = $this->own($id);

        if (!$quote) {
            return null;
        }

        $invoice = \Paymenter\Extensions\Others\Quotes\Support\Quoting::accept($quote);

        if (!$invoice) {
            return null;
        }

        // Straight to the invoice: they have just agreed to pay, and the next thing they
        // want is the way to do it.
        return $this->redirect(route('invoices.show', $invoice->id), navigate: true);
    }

    /** The customer declines. Kept rather than deleted — a declined quote is a sales record. */
    public function decline(int $id): void
    {
        $quote = $this->own($id);

        if ($quote) {
            \Paymenter\Extensions\Others\Quotes\Support\Quoting::decline($quote);
        }
    }

    /** A quote that is this customer's and still answerable, or null. */
    private function own(int $id)
    {
        if (!$this->available()) {
            return null;
        }

        $quote = Quote::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->with('items')
            ->first();

        return $quote?->isOpen() ? $quote : null;
    }

    /** Whether the quoting extension is installed, judged by its table rather than its class. */
    private function available(): bool
    {
        return class_exists(Quote::class)
            && Schema::hasTable('ext_quotes');
    }
}
