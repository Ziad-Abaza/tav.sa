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
        if (Schema::hasTable('fb_messenger_campaigns')) {
            return;
        }

        Schema::create('fb_messenger_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rel_type')->nullable();
            $table->text('body_params')->nullable();
            $table->foreignId('fb_template_id')->constrained('facebook_messenger_templates')->cascadeOnDelete();
            $table->timestamp('scheduled_send_time')->nullable();
            $table->boolean('send_now')->default(false);
            $table->boolean('pause_campaign')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->unsignedInteger('sending_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->boolean('select_all')->default(false);
            $table->json('rel_data')->nullable();
            $table->json('variables_json')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_sent']);
            $table->index(['scheduled_send_time', 'send_now', 'pause_campaign', 'is_sent'], 'fb_campaigns_schedule_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fb_messenger_campaigns');
    }
};

