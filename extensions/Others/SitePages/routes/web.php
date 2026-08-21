<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\SitePages\Livewire\ContactUs;
use Paymenter\Extensions\Others\SitePages\Livewire\NetworkStatus;

// Public: a visitor checks status or asks a question before they have an account.
Route::group(['middleware' => ['web']], function () {
    Route::get('/network-status', NetworkStatus::class)->name('network-status');
    Route::get('/contact', ContactUs::class)->name('contact');

    // The reference portal's "View RSS Feed" link beside the news list.
    //
    // Deliberately NOT /announcements/rss: the Announcements extension registers
    // `announcements/{announcement}` and boot order is not guaranteed, so that path could
    // be captured as an announcement slug and 404 instead of returning the feed.
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
    })->name('announcements.rss');
});
