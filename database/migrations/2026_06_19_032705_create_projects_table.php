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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('version')->nullable();
            $table->string('github_url')->nullable();
            $table->string('package_file')->nullable();
            $table->string('package_external_url')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', [
                'project_internal',
                'project_client',
                'wp_theme',
                'wp_plugin',
                'wp_theme_child',
            ]);
            $table->foreignId('parent_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
