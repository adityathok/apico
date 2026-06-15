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
        Schema::table('request_logs', function (Blueprint $table) {
            $table->foreignId('website_id')->nullable()->change();
            $table->foreignId('license_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->foreignId('website_id')->nullable(false)->change();
            $table->foreignId('license_id')->nullable(false)->change();
        });
    }
};
