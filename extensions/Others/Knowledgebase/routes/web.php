<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase\Index;
use Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase\Show;

// Public: the knowledgebase is how a visitor answers their own question before buying,
// so it is deliberately not behind auth.
Route::group(['middleware' => ['web']], function () {
    Route::get('/knowledgebase', Index::class)->name('knowledgebase.index');
    Route::get('/knowledgebase/category/{category}', Index::class)->name('knowledgebase.category');
    Route::get('/knowledgebase/{article}', Show::class)->name('knowledgebase.show');
});
