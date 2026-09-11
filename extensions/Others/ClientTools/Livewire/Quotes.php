<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Paymenter\Extensions\Others\Quotes\Models\Quote;

/** My Quotes. */
class Quotes extends Component
{
    public function render()
    {
        return view('clienttools::quotes', ['quotes' => $this->quotes()]);
    }

    /**
     * The customer's own quotes, newest first — drafts excluded.
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

    /** The customer accepts — which raises a real invoice for the full amount. */
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
