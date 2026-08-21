<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\ClientTools\Livewire\Addons;
use Paymenter\Extensions\Others\ClientTools\Livewire\Contacts;
use Paymenter\Extensions\Others\ClientTools\Livewire\Downloads;
use Paymenter\Extensions\Others\ClientTools\Livewire\EmailHistory;
use Paymenter\Extensions\Others\ClientTools\Livewire\MassPayment;
use Paymenter\Extensions\Others\ClientTools\Livewire\Quotes;
use Paymenter\Extensions\Others\ClientTools\Livewire\UserManagement;

// Downloads is the one public page — the reference portal shows it to visitors, with the
// customer-only files filtered out by Download::visibleTo().
Route::group(['middleware' => ['web']], function () {
    Route::get('/downloads', Downloads::class)->name('downloads');
});

// Everything else is account data and needs a session.
Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/quotes', Quotes::class)->name('quotes');
    Route::get('/billing/mass-payment', MassPayment::class)->name('mass-payment');
    Route::get('/account/contacts', Contacts::class)->name('account.contacts');
    Route::get('/account/users', UserManagement::class)->name('account.users');
    Route::get('/account/email-history', EmailHistory::class)->name('account.email-history');
    // Not /services/addons: core registers `services/{service}` first, which would match
    // "addons" as a service slug and 404 before this route was ever reached.
    Route::get('/addons', Addons::class)->name('addons');
});
