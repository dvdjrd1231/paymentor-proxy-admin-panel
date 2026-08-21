<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;

/**
 * View Available Addons.
 *
 * The reference portal lists what an existing service can be extended with. Paymenter
 * models that as product upgrades (`product_upgrades`, surfaced by Service::upgradable
 * and Service::productUpgrades), so each active service is paired with the products it
 * can move up to and linked at the core upgrade flow — no parallel purchase path, so
 * pricing, proration and provisioning all stay in core's hands.
 */
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
