<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-counter support: 10+ counters ab ek hi company me alag-alag local
     * ids generate karte hain, isliye local_id mapping me device_id (aur string
     * keys ke liye local_ref) add karte hain. Iske bina 10 counters ke same
     * local_id ek dusre ki mapping clobber kar ke duplicate rows bana dete the.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sync_local_mappings')) {
            return;
        }

        if (! Schema::hasColumn('sync_local_mappings', 'device_id')) {
            Schema::table('sync_local_mappings', function (Blueprint $table) {
                $table->string('device_id', 64)->default('')->after('local_id');
            });
        }

        if (! Schema::hasColumn('sync_local_mappings', 'local_ref')) {
            Schema::table('sync_local_mappings', function (Blueprint $table) {
                $table->string('local_ref', 100)->default('')->after('device_id');
            });
        }

        // Purana (local_id-only) unique constraint hatao — wo multi-counter me collision deta tha.
        try {
            Schema::table('sync_local_mappings', function (Blueprint $table) {
                $table->dropUnique('sync_map_unique');
            });
        } catch (\Throwable) {
            // index already gone — ignore
        }

        try {
            Schema::table('sync_local_mappings', function (Blueprint $table) {
                $table->unique(['entity_type', 'device_id', 'local_ref', 'local_id', 'company_id', 'outlet_id'], 'sync_map_device_unique');
            });
        } catch (\Throwable) {
            // duplicate index — ignore (migration re-run safety)
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sync_local_mappings')) {
            return;
        }

        try {
            Schema::table('sync_local_mappings', function (Blueprint $table) {
                $table->dropUnique('sync_map_device_unique');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('sync_local_mappings', function (Blueprint $table) {
                $table->unique(['entity_type', 'local_id', 'company_id', 'outlet_id'], 'sync_map_unique');
            });
        } catch (\Throwable) {
        }
    }
};
