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
        if (Schema::hasTable('fb_messenger_campaign_details')) {
            return;
        }

        Schema::create('fb_messenger_campaign_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('fb_messenger_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id');
            $table->string('facebook_psid');
            $table->text('message_text')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1=pending, 2=sent, 0=failed');
            $table->string('message_status')->default('pending');
            $table->string('fb_message_id')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['tenant_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_messenger_campaign_details');
    }
};
