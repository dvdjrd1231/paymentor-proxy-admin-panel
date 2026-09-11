<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's client Notes tab: a list of dated notes, each by a named admin, with a
 * sticky one pinned to the top.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ao_client_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->boolean('sticky')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'sticky']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ao_client_notes');
    }
};
