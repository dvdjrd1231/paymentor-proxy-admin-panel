<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\SitePages\Livewire\ContactUs;
use Paymenter\Extensions\Others\SitePages\Livewire\NetworkStatus;

// Network Status is behind auth, matching the reference portal: it reports on the service
// customers are paying for, so a signed-out visitor is sent to the login page rather than
// shown the incident feed.
Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/network-status', NetworkStatus::class)->name('network-status');
});

// Contact Us stays public: it is the one page someone without an account needs in order to
// reach anybody, and gating it would leave a locked-out customer with nowhere to go.
Route::group(['middleware' => ['web']], function () {
    Route::get('/contact', ContactUs::class)->name('contact');

    // The reference portal's "View RSS Feed" link.
    //
    // Announcements ships its own feed, but it is dead: its routes file registers
    // `announcements/{announcement:slug}` *before* `announcements/rss`, so the slug route
    // captures "rss", route-model binding finds no such announcement and the feed 404s.
    // That extension is vendored upstream, so it is not edited here.
    //
    // This route is mounted at /rss/announcements, which no wildcard can shadow, and under
    // its own name — reusing `announcements.rss` would collide with the upstream name and
    // route() would resolve to whichever extension booted last.
    Route::get('/rss/announcements', function () {
        $model = 'Paymenter\Extensions\Others\Announcements\Models\Announcement';

        $items = collect();
        if (class_exists($model)) {
            try {
                $items = $model::where('is_active', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderByDesc('published_at')
                    ->limit(50)
                    ->get();
            } catch (\Throwable $e) {
                $items = collect();
            }
        }

        return response()
            ->view('sitepages::announcements.rss', ['items' => $items])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    })->name('sitepages.announcements.rss');
});
