<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Client Profile's Files panel (Leandro, 2026-09-12: the reference has one and ours
 * only said "No files uploaded"). Core keeps no per-client storage, so this is ours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_client_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index();
            // Who put it there, so the panel can say. Nullable because an admin account
            // may be deleted long after the file was uploaded.
            $table->foreignId('uploaded_by')->nullable();
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('filesize')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_client_files');
    }
};
