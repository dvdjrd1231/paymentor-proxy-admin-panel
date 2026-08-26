<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each administrator's dashboard layout lives.
 *
 * One row per admin, not per admin per widget: the whole layout is read on every dashboard
 * render and written whole on every change, so a row per widget would be several queries to
 * answer one question and a delete to answer "this widget no longer exists".
 *
 * `order` and `hidden` hold Livewire component names — `paymenter.extensions...at-a-glance`
 * — because that is the only identifier that survives a page load. A widget that has since
 * been uninstalled simply stops matching and is ignored; nothing has to clean up after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_adminops_dashboard_layouts', function (Blueprint $table) {
            $table->id();

            // Cascades: a deleted administrator's dashboard preferences are of no interest
            // to anyone, and a stale row would be handed to whoever inherits the id.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->json('order')->nullable();
            $table->json('hidden')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_adminops_dashboard_layouts');
    }
};
