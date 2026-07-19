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
        if (! Schema::hasTable('template_bots')) {
            return;
        }

        Schema::table('template_bots', function (Blueprint $table) {
            // Add JSON column for multiple media IDs F(header and carousel cards)
            if (! Schema::hasColumn('template_bots', 'whatsapp_media_ids')) {
                $table->json('whatsapp_media_ids')->nullable()->after('filename');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_bots', function (Blueprint $table) {
            if (Schema::hasColumn('template_bots', 'whatsapp_media_ids')) {
                $table->dropColumn('whatsapp_media_ids');
            }
        });
    }
};
