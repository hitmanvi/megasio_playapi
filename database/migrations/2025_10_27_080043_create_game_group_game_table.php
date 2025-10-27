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
        Schema::create('game_group_game', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_group_id')->comment('游戏群组ID');
            $table->unsignedBigInteger('game_id')->comment('游戏ID');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->timestamps();
            
            // 添加索引
            $table->index('game_group_id');
            $table->index('game_id');
            $table->index('sort_id');
            $table->unique(['game_group_id', 'game_id']); // 确保同一个游戏不会在同一个群组中重复
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_group_game');
    }
};
