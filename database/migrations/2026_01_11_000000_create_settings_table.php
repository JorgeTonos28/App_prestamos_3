<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // Add columns if they don't exist (using the generic key/value structure,
        // but for specific structured data we might want columns?
        // Wait, the Settings model uses key/value pairs.
        // The user request implies "columns" in my plan, but looking at SettingsController, it uses `Setting::updateOrCreate(['key' => ...])`.
        // So I don't need new columns in the `settings` table if it follows the Key-Value pattern.
        // The `settings` table structure seen in `SettingsController` implies it's just key-value.
        // So I just need to ensure the table exists.
        // However, if I want to seed default values, I can do it here.

        // I will add the dark logo key and email keys in the Seeder or just let the Controller handle it.
        // The Controller saves them as keys.
        // So the schema requirement is just the `settings` table with key/value.
        // I will stick to the Key-Value pattern as it is already established.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't want to drop it if it existed before, but for this migration 'up' was conditional.
        // It's safer to not drop it in down() if we are unsure, or drop it if we created it.
        // But since this is a fix-forward, I will leave down empty or just Schema::dropIfExists('settings') if I am sure.
        // I'll leave it empty to be safe against data loss.
    }
};
