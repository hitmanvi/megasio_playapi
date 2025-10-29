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
        Schema::create('game_theme', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->comment('游戏ID');
            $table->foreignId('theme_id')->comment('主题ID');
            $table->timestamps();
            
            // 添加索引
            $table->index('game_id');
            $table->index('theme_id');
            $table->unique(['game_id', 'theme_id']); // 确保同一个游戏不会重复关联同一个主题
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_theme');
    }
};
