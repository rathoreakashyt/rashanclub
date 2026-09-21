<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bug #1 Fix: Fix corrupted tokenable_type in personal_access_tokens
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'AppModelsUser')
            ->update(['tokenable_type' => 'App\\Models\\User']);

        // Bug #2 Fix: Add missing columns to customers table
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'work_address')) {
                $table->string('work_address')->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers', 'guarantor_name')) {
                $table->string('guarantor_name')->nullable()->after('work_address');
            }
            if (!Schema::hasColumn('customers', 'guarantor_mobile')) {
                $table->string('guarantor_mobile')->nullable()->after('guarantor_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['work_address', 'guarantor_name', 'guarantor_mobile']);
        });

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->update(['tokenable_type' => 'AppModelsUser']);
    }
};
