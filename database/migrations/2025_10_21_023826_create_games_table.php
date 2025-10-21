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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id');
            $table->foreignId('category_id');
            $table->foreignId('theme_id');
            $table->string('out_id');
            $table->string('name');
            $table->string('thumbnail');
            $table->unsignedInteger('sort_id');
            $table->boolean('enabled')->default(false);
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
