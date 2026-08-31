<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Support wing, the parts Paymenter has no tables for: predefined-reply
 * categories (the replies themselves live in TicketTools' `canned_responses`, whose
 * `department` column holds the category name), the Downloads area, and Network Issues.
 */
return new class extends Migration
{
    public function up(): void
    {
        // hasTable guards: a failed first run leaves the earlier tables standing, and this
        // migration must be able to finish the job rather than trip over them.
        Schema::hasTable('ext_predefined_reply_categories') || Schema::create('ext_predefined_reply_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::hasTable('ext_download_categories') || Schema::create('ext_download_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::hasTable('ext_downloads') || Schema::create('ext_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('ext_download_categories')->nullOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('filesize')->default(0);
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
        });

        Schema::hasTable('ext_network_issues') || Schema::create('ext_network_issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // server | system | other — the reference's Type select.
            $table->string('type')->default('other');
            // Plain column, no FK: core's servers table keys don't accept the constraint
            // (errno 150 on dev), and belongsTo needs only the column.
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->string('priority')->default('medium');
            // scheduled | investigating | in_progress | outage | resolved
            $table->string('status')->default('investigating');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_network_issues');
        Schema::dropIfExists('ext_downloads');
        Schema::dropIfExists('ext_download_categories');
        Schema::dropIfExists('ext_predefined_reply_categories');
    }
};
