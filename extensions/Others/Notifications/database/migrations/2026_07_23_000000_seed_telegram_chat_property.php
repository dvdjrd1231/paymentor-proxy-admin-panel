<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds an optional "Telegram Chat ID" field to customer profiles (a Paymenter
 * custom property on the User model). Customers who fill it in will receive their
 * notifications on Telegram in addition to email/in-app.
 *
 * Idempotent: insertOrIgnore keyed on the unique `key`.
 */
return new class extends Migration
{
    private const KEY = 'telegram_chat_id';

    public function up(): void
    {
        DB::table('custom_properties')->insertOrIgnore([
            [
                'key' => self::KEY,
                'name' => 'Telegram Chat ID',
                'model' => 'App\Models\User',
                'type' => 'string',
                'non_editable' => 0,
                'required' => 0,
                'show_on_invoice' => 0,
                'validation' => 'nullable|string|max:64',
            ],
        ]);
    }

    public function down(): void
    {
        // Removing the definition cascades to stored values (FK on custom_property_id).
        DB::table('custom_properties')->where('key', self::KEY)->delete();
    }
};
