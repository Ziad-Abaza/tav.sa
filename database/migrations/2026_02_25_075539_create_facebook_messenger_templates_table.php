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
        if (Schema::hasTable('facebook_messenger_templates')) {
            return;
        }

        Schema::create('facebook_messenger_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('content_type', ['text', 'image', 'video', 'document', 'audio'])->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->text('message_text')->nullable();
            $table->json('buttons')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('copied_from_template_id')->nullable();
            $table->unsignedInteger('sending_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_messenger_templates');
    }
};
