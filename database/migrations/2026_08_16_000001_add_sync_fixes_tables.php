<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sync Conflict Fix v2.0 — additive only.
     * 1) idempotency_keys  — duplicate push prevention (FIX 1)
     * 2) sync_dead_letters — permanent failure log, no silent drops (FIX 4)
     * 3) sync_version      — logical clock for LWW, clock-skew fix (FIX 2)
     */
    public function up(): void
    {
        // ─── FIX 1: Idempotency keys ───
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->string('device_id', 100)->default('');
            $table->json('response_snapshot')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['key', 'device_id']);
            $table->index('expires_at');
        });

        // ─── FIX 4: Dead letters ───
        Schema::create('sync_dead_letters', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100)->default('');
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('entity_type', 60)->default('');
            $table->string('local_id', 100)->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('first_failed_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->index(['device_id', 'entity_type']);
            $table->index('resolved');
            $table->index('outlet_id');
        });

        // ─── FIX 2: sync_version logical clock on shared master tables ───
        $versionTables = [
            'items', 'item_categories', 'brands', 'units', 'racks', 'variations',
            'suppliers', 'customers', 'promotions', 'price_lists', 'price_list_items',
            'payment_methods', 'counters', 'expense_categories',
        ];

        foreach ($versionTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'sync_version')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->bigInteger('sync_version')->unsigned()->default(1);
                });
            }
            try {
                DB::statement("CREATE INDEX idx_{$table}_sync_version ON {$table}(sync_version)");
            } catch (\Throwable $e) {
                // index pehle se ho to ignore
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('sync_dead_letters');
    }
};