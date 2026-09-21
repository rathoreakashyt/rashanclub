<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('installed_modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->string('author')->nullable();
            $table->string('zip_path')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('del_status')->default('Live');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('installed_modules');
    }
};
