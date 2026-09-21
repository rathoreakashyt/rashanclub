<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sync_conflicts')) {
            Schema::create('sync_conflicts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('outlet_id')->index();
                $table->string('device_id', 100)->nullable();
                $table->string('entity_type', 100);
                $table->string('entity_key', 255);
                $table->unsignedBigInteger('server_id')->nullable();
                $table->longText('incoming_json')->nullable();
                $table->longText('existing_json')->nullable();
                $table->dateTime('incoming_updated_at')->nullable();
                $table->dateTime('existing_updated_at')->nullable();
                // server_won | desktop_won | manual
                $table->string('resolution', 20)->default('server_won');
                $table->timestamps();

                $table->index(['company_id', 'entity_type', 'entity_key'], 'idx_conflicts_entity');
                $table->index(['company_id', 'device_id'], 'idx_conflicts_device');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
