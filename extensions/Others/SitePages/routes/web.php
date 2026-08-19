<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\SitePages\Livewire\ContactUs;
use Paymenter\Extensions\Others\SitePages\Livewire\NetworkStatus;

// Public: a visitor checks status or asks a question before they have an account.
Route::group(['middleware' => ['web']], function () {
    Route::get('/network-status', NetworkStatus::class)->name('network-status');
    Route::get('/contact', ContactUs::class)->name('contact');
});
