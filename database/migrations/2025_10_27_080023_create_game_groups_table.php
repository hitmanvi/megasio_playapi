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
        Schema::create('game_groups', function (Blueprint $table) {
            $table->id();
            $table->string('category')->comment('分类：Event、System');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->integer('app_limit')->nullable()->comment('App端显示游戏数量限制');
            $table->integer('web_limit')->nullable()->comment('Web端显示游戏数量限制');
            $table->boolean('enabled')->default(false)->comment('是否启用');
            $table->timestamps();
            
            // 添加索引
            $table->index('category');
            $table->index('enabled');
            $table->index('sort_id');
            $table->index(['category', 'enabled', 'sort_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_groups');
    }
};
