<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps a desktop-app (SQLite) local_id to the server row created for it.
     * Used by /api/sync/push-entity so retries are idempotent and deletes can
     * resolve the correct server row even across multiple devices/outlets.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sync_local_mappings')) {
            Schema::create('sync_local_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 100);
                $table->bigInteger('local_id');
                $table->bigInteger('server_id');
                $table->bigInteger('company_id')->unsigned();
                $table->bigInteger('outlet_id')->unsigned();
                $table->timestamps();

                $table->unique(['entity_type', 'local_id', 'company_id', 'outlet_id'], 'sync_map_unique');
                $table->index(['entity_type', 'company_id'], 'sync_map_type_company');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_local_mappings');
    }
};
