<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where each administrator's dashboard layout lives. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_adminops_dashboard_layouts') || Schema::create('ext_adminops_dashboard_layouts', function (Blueprint $table) {
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
