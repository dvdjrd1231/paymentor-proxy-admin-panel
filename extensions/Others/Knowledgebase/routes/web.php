<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase\Index;
use Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase\Show;

// Behind auth, matching the reference portal, where the knowledgebase is a customer
// resource rather than a public one: a visitor who follows the menu entry while signed out
// is sent to the login page ("This page is restricted") instead of reading the articles.
Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/knowledgebase', Index::class)->name('knowledgebase.index');
    Route::get('/knowledgebase/category/{category}', Index::class)->name('knowledgebase.category');
    Route::get('/knowledgebase/{article}', Show::class)->name('knowledgebase.show');
});
