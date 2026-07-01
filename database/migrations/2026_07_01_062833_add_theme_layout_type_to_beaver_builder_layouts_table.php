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
        Schema::table('beaver_builder_layouts', function (Blueprint $table) {
            $table->string('theme_layout_type', 20)->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beaver_builder_layouts', function (Blueprint $table) {
            $table->dropColumn('theme_layout_type');
        });
    }
};
