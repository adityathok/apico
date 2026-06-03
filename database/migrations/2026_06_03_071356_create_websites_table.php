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
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('ip_address')->nullable();
            $table->string('license_key')->unique();
            $table->enum('status', ['active', 'invalid'])->default('active');
            $table->string('theme_version')->nullable();
            $table->string('plugin_version')->nullable();
            $table->string('wp_version')->nullable();
            $table->string('php_version')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
