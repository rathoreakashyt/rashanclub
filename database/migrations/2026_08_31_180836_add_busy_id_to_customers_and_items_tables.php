<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add busy_id to customers table
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'busy_id')) {
                $table->unsignedBigInteger('busy_id')->nullable()->after('id');
                $table->index('busy_id');
            }
        });

        // Add busy_id to items table
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'busy_id')) {
                $table->unsignedBigInteger('busy_id')->nullable()->after('id');
                $table->index('busy_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['busy_id']);
            $table->dropColumn('busy_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['busy_id']);
            $table->dropColumn('busy_id');
        });
    }
};
