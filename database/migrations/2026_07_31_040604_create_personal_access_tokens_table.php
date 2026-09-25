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
        if (Schema::hasTable('personal_access_tokens'))
        {
            // Fix: ensure id column is auto-increment (older installs may have broken table)
            if (Schema::hasColumn('personal_access_tokens', 'id'))
            {
                $engine = DB::getDriverName();
                if ($engine === 'mysql')
                {
                    DB::statement('ALTER TABLE `personal_access_tokens` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT');
                }
            }
            return;
        }
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
