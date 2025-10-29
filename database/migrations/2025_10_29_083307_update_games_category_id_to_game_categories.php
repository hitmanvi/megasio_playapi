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
        Schema::table('games', function (Blueprint $table) {
            // Drop the old foreign key constraint
            $table->dropForeign(['category_id']);
        });
        
        // Note: The actual data migration from tags to game_categories 
        // will be handled in GameCategorySeeder
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Restore foreign key to tags table
            $table->foreign('category_id')->references('id')->on('tags');
        });
    }
};
