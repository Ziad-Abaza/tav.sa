<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    	if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'whatsapp_media_ids')) {
                    $table->json('whatsapp_media_ids')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
     	if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (Schema::hasColumn('campaigns', 'whatsapp_media_ids')) {
                    $table->dropColumn('whatsapp_media_ids');
                }
            });
        }
    }
};
