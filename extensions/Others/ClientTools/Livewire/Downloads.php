<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\ClientTools\Models\Download;

/**
 * Downloads — setup guides, proxy configuration files and tooling the operator publishes.
 *
 * Entries are grouped by category, as the reference portal groups them. A guest sees only
 * the entries marked public; the customer-only ones need a session.
 */
class Downloads extends Component
{
    /**
     * Count the click and hand back the URL.
     *
     * The counter is incremented atomically so simultaneous downloads cannot lose a tick,
     * and the row is re-read through the visibility scope so a login-only file cannot be
     * fetched by posting its id while signed out.
     */
    public function download(int $id)
    {
        $download = Download::visibleTo(Auth::check())->find($id);

        if (!$download) {
            return $this->notify(__('clienttools.download_unavailable'), 'error');
        }

        $download->increment('download_count');

        return redirect()->away($download->url);
    }

    public function render()
    {
        $downloads = Download::visibleTo(Auth::check())
            ->orderBy('sort')
            ->orderBy('title')
            ->get()
            ->groupBy(fn ($d) => $d->category ?: __('clienttools.downloads_uncategorised'));

        return view('clienttools::downloads', ['groups' => $downloads]);
    }
}
