<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\SitePages\Livewire\ContactUs;
use Paymenter\Extensions\Others\SitePages\Livewire\NetworkStatus;

// Behind auth, matching the reference: it reports on the service customers pay for.
Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/network-status', NetworkStatus::class)->name('network-status');
});

// Contact Us stays public — a locked-out customer needs one way to reach somebody.
Route::group(['middleware' => ['web']], function () {
    Route::get('/contact', ContactUs::class)->name('contact');

    // The reference's "View RSS Feed" link. Announcements ships a feed but it 404s: its
    // slug route `announcements/{announcement:slug}` is registered before `announcements/rss`
    // and captures "rss". Mounted at /rss/announcements, which no wildcard can shadow, under
    // its own name — reusing `announcements.rss` would collide with the upstream name.
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
