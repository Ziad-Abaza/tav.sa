<?php

namespace Database\Seeders;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class SyncDatabaseFields extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                // Only add columns if they don't exist
                if (! Schema::hasColumn('subscriptions', 'metadata')) {
                    $table->text('metadata')->nullable();
                }
            });
        }
    }
}
