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
        if (! Schema::hasTable('message_bots')) {
            return;
        }

        Schema::table('message_bots', function (Blueprint $table) {
            if (! Schema::hasColumn('message_bots', 'whatsapp_media_id')) {
                $table->string('whatsapp_media_id')->nullable()->after('filename')->comment('Pre-uploaded WhatsApp media ID to avoid 429 rate limiting');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('message_bots')) {
            return;
        }

        Schema::table('message_bots', function (Blueprint $table) {
            if (Schema::hasColumn('message_bots', 'whatsapp_media_id')) {
                $table->dropColumn('whatsapp_media_id');
            }
        });
    }
};
