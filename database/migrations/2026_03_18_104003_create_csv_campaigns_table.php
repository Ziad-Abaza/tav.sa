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
        if (! Schema::hasTable('csv_campaigns')) {
            Schema::create('csv_campaigns', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('template_id');

                $table->string('csv_file_path');

                $table->json('header_params')->nullable();
                $table->json('body_params')->nullable();
                $table->json('footer_params')->nullable();
                $table->json('button_params')->nullable();
                $table->json('cards_params')->nullable();

                $table->json('whatsapp_media_ids')->nullable();
                $table->string('filename')->nullable();

                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('sending_count')->default(0);

                $table->timestamp('scheduled_send_time')->nullable();
                $table->boolean('send_now')->default(false);

                $table->boolean('is_sent')->default(false)->index();
                $table->boolean('pause_campaign')->default(false);

                $table->timestamps();

                $table->index(['tenant_id', 'is_sent']);
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('csv_campaigns')) {
            Schema::dropIfExists('csv_campaigns');
        }
    }
};
