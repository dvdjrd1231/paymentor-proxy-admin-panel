<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;

/** View Available Addons. */
class Addons extends Component
{
    public function render()
    {
        $services = Auth::user()->services()
            ->where('status', 'active')
            ->with('product.category')
            ->get();

        // Only services that actually have somewhere to upgrade to are worth listing;
        // an entry that leads to an empty upgrade page is just a dead end.
        $rows = $services
            ->map(fn ($service) => [
                'service' => $service,
                'upgrades' => $service->productUpgrades(),
            ])
            ->filter(fn ($row) => $row['upgrades']->isNotEmpty())
            ->values();

        return view('clienttools::addons', ['rows' => $rows]);
    }
}
