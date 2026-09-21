<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'busynotify_token')) {
                $table->text('busynotify_token')->nullable()->after('smtp_details');
            }
            if (!Schema::hasColumn('companies', 'busynotify_company_id')) {
                $table->string('busynotify_company_id', 50)->nullable()->after('busynotify_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['busynotify_token', 'busynotify_company_id']);
        });
    }
};
