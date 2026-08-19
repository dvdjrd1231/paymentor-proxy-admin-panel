<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_kb_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ext_kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('ext_kb_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->dateTime('published_at')->nullable();

            // Incremented on each article view, so the client area can show what customers
            // actually read — the reference portal surfaces popular articles this way.
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'published_at']);
        });
    }

    public function down(): void
    {
        // Articles first: they hold the foreign key.
        Schema::dropIfExists('ext_kb_articles');
        Schema::dropIfExists('ext_kb_categories');
    }
};
