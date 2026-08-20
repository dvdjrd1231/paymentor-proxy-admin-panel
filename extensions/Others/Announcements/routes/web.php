<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\Announcements\Livewire\Announcements\Index;
use Paymenter\Extensions\Others\Announcements\Livewire\Announcements\Show;

Route::group(['middleware' => ['web']], function () {
    Route::get('/announcements', Index::class)->name('announcements.index');
    Route::get('/announcements/{announcement:slug}', Show::class)->name('announcements.show');
    Route::get('/announcements/rss', function () {
        $items = \Paymenter\Extensions\Others\Announcements\Models\Announcement::query()
            ->where('is_active', true)
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->get();

        return response()->view('announcements::rss', compact('items'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    })->name('announcements.rss');
});
