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
        if (! Schema::hasTable('themes')) {
            Schema::create('themes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('folder')->unique();
                $table->boolean('active')->default(false);
                $table->string('version')->nullable()->default('1.0');
                $table->string('theme_url')->nullable();
                $table->longText('payload')->nullable();
                $table->longText('theme_html')->nullable();
                $table->longText('theme_css')->nullable();
                $table->enum('type', ['core', 'custom'])->default('core');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
