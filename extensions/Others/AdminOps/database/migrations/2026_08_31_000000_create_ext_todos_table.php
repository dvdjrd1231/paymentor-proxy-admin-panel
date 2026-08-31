<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The reference's To-Do List: staff notes with a due date and a done flag. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_todos') || Schema::create('ext_todos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->boolean('done')->default(false);
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_todos');
    }
};
