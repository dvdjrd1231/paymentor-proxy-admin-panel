<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\SitePages\Livewire\ContactUs;
use Paymenter\Extensions\Others\SitePages\Livewire\NetworkStatus;

// Public: a visitor checks status or asks a question before they have an account.
Route::group(['middleware' => ['web']], function () {
    Route::get('/network-status', NetworkStatus::class)->name('network-status');
    Route::get('/contact', ContactUs::class)->name('contact');

    // No RSS route here: the Announcements extension already registers one at
    // /announcements/rss under the name `announcements.rss`. Adding a second route with
    // the same name is not an error Laravel reports — the later registration simply wins
    // route() lookups — so the "View RSS Feed" link would have resolved to whichever
    // extension happened to boot last. The archive rail links the shipped feed instead.
});
