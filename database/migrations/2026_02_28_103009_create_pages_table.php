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
    Schema::create('pages', function (Blueprint $table) {
        $table->id();
        $table->string('title')->default('Untitled Page');
        $table->string('slug')->unique()->nullable();
        $table->longText('html_content')->nullable(); // What the user sees
        $table->longText('css_content')->nullable();  // The styles
        $table->json('json_content')->nullable();     // The editor state (for GrapesJS)
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
