<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_widget_settings')) {
            return;
        }

        Schema::create('user_widget_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dashboard', 100)->default('main');
            $table->json('widget_order')->nullable();
            $table->json('visibility')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'dashboard']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_widget_settings');
    }
};
