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
        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('template_id')->nullable();
                $table->string('template_name');
                $table->string('language');
                $table->string('status');
                $table->string('category');
                $table->string('header_data_format')->nullable();
                $table->text('header_data_text')->nullable();
                $table->integer('header_params_count')->nullable();
                $table->text('body_data')->nullable();
                $table->integer('body_params_count')->nullable();
                $table->text('footer_data')->nullable();
                $table->integer('footer_params_count')->nullable();
                $table->text('buttons_data')->nullable();
                $table->integer('message_send_ttl_seconds')->nullable();
                $table->boolean('add_security_recommendation')->default(false);
                $table->integer('code_expiration_minutes')->nullable();
                $table->json('otp_button_config')->nullable();
                $table->text('header_file_url')->nullable();
                $table->json('header_variable_value')->nullable();
                $table->json('body_variable_value')->nullable();
                $table->string('template_type', 50)->nullable();
                $table->longText('cards_json')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
