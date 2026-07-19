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
        if (! Schema::hasTable('otp_verifications')) {
            Schema::create('otp_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('phone', 20)->index();
                $table->string('code', 10);
                $table->string('purpose', 50)->default('authentication'); // authentication, login, registration, password_reset, etc.
                $table->string('template_name', 100)->nullable();
                $table->integer('expiry_minutes')->default(10);
                $table->timestamp('expires_at');
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->integer('verification_attempts')->default(0);
                $table->integer('max_attempts')->default(5);
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable(); // For storing additional context
                $table->timestamps();

                // Indexes for performance
                $table->index(['tenant_id', 'phone', 'is_verified']);
                $table->index(['expires_at', 'is_verified']);
                $table->unique(['tenant_id', 'phone', 'code', 'purpose']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
