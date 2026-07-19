<?php

use App\Models\Tenant;
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
        // Get all tenants and add facebook_psid column to their contacts tables
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tableName = $tenant->subdomain.'_contacts';

            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'facebook_psid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('facebook_psid')->nullable()->after('phone')->index();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tableName = $tenant->subdomain.'_contacts';

            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'facebook_psid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('facebook_psid');
                });
            }
        }
    }
};
