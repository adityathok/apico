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
        Schema::create('beaver_builder_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type'); // theme-layout, template-layout, row, module
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->string('screenshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beaver_builder_layouts');
    }
};
