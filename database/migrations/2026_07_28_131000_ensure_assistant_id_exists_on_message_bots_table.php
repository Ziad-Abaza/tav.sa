<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_bots') && ! Schema::hasColumn('message_bots', 'assistant_id')) {
            Schema::table('message_bots', function (Blueprint $table) {
                $table->unsignedBigInteger('assistant_id')
                    ->nullable()
                    ->after('whatsapp_media_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('message_bots') && Schema::hasColumn('message_bots', 'assistant_id')) {
            Schema::table('message_bots', function (Blueprint $table) {
                $table->dropColumn('assistant_id');
            });
        }
    }
};
