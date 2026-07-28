<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        if (! Schema::hasColumn('campaigns', 'cards_params')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->json('cards_params')
                    ->nullable()
                    ->comment('Stores carousel card parameters for campaigns');
            });
        }

        if (! Schema::hasColumn('campaigns', 'whatsapp_media_ids')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->json('whatsapp_media_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        if (Schema::hasColumn('campaigns', 'whatsapp_media_ids')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('whatsapp_media_ids');
            });
        }

        if (Schema::hasColumn('campaigns', 'cards_params')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('cards_params');
            });
        }
    }
};
