<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Files sent with a notification template — the reference's Attachments row. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_email_template_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('template_id')->index();
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('filesize')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_email_template_attachments');
    }
};
