<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketing_logs')) {
            Schema::create('marketing_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('company_id')->index();
                $table->integer('user_id')->nullable();
                $table->string('channel', 20); // sms, whatsapp, email
                $table->string('to', 255);
                $table->string('subject', 255)->nullable();
                $table->text('message');
                $table->string('status', 20)->default('pending'); // pending, sent, failed
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_logs');
    }
};
