<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's "Save Message" on Send Email Message, and the "Load Saved Message" picker
 * that reads them back. A message staff write often — a payment chase, a maintenance
 * notice — is kept by name and reloaded rather than retyped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_saved_messages')) {
            return;
        }

        Schema::create('ext_saved_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('subject');
            $table->text('body');
            // Who saved it. Kept for the audit trail; the message stays if they leave.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_saved_messages');
    }
};
