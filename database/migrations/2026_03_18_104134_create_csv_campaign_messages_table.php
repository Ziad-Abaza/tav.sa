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
        if (! Schema::hasTable('csv_campaign_messages')) {
            Schema::create('csv_campaign_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->foreignId('csv_campaign_id')
                    ->constrained('csv_campaigns')
                    ->cascadeOnDelete();

                $table->string('phone', 20);

                $table->json('contact_data');

                $table->tinyInteger('status')->default(1)->index();

                $table->enum('message_status', ['pending', 'sent', 'delivered', 'read', 'failed'])
                    ->default('pending')
                    ->index();

                $table->string('whatsapp_id')->nullable()->index();

                $table->text('response_message')->nullable();

                $table->timestamps();

                // Performance indexes
                $table->index(['csv_campaign_id', 'status']);
                $table->index(['csv_campaign_id', 'message_status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('csv_campaign_messages')) {
            Schema::dropIfExists('csv_campaign_messages');
        }
    }
};
