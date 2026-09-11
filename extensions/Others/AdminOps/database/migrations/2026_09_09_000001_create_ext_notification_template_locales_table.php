<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localised versions of an email template — the reference's Manage Languages, which
 * activates a language so a template can carry a translation of its subject and body
 * (Leandro's screenshots, 2026-09-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_notification_template_locales')) {
            return;
        }

        Schema::create('ext_notification_template_locales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_template_id');
            $table->string('locale', 12);
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();

            // One translation per template per language, and the lookup the send path uses.
            $table->unique(['notification_template_id', 'locale'], 'ext_ntl_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_notification_template_locales');
    }
};
