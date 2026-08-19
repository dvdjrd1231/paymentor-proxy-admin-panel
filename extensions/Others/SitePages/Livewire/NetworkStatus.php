<?php

namespace Paymenter\Extensions\Others\SitePages\Livewire;

use Livewire\Component;

class NetworkStatus extends Component
{
    public function render()
    {
        // Announcements double as incident notices, exactly as the reference portal does —
        // its Network Status page is a filtered announcement feed, not a separate system.
        // Resolved dynamically so this page works whether or not that extension is enabled.
        $incidents = collect();
        $model = 'Paymenter\Extensions\Others\Announcements\Models\Announcement';

        if (class_exists($model)) {
            try {
                $incidents = $model::where('is_active', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderByDesc('published_at')
                    ->limit(20)->get();
            } catch (\Throwable $e) {
                // Table missing (extension never installed) — show the all-clear instead
                // of a 500. An outage page that itself errors is the worst outcome.
                $incidents = collect();
            }
        }

        return view('sitepages::network-status', ['incidents' => $incidents]);
    }
}
