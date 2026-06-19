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
        if (Schema::hasColumn('projects', 'package_file_url') && ! Schema::hasColumn('projects', 'package_file')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->renameColumn('package_file_url', 'package_file');
            });
        }

        if (! Schema::hasColumn('projects', 'package_external_url')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('package_external_url')->nullable()->after('package_file');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('projects', 'package_external_url')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('package_external_url');
            });
        }

        if (Schema::hasColumn('projects', 'package_file') && ! Schema::hasColumn('projects', 'package_file_url')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->renameColumn('package_file', 'package_file_url');
            });
        }
    }
};
