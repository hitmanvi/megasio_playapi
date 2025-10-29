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
        Schema::create('game_categories', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable()->comment('图标路径或类名');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->timestamps();
            
            // 添加索引
            $table->index('enabled');
            $table->index('sort_id');
            $table->index(['enabled', 'sort_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_categories');
    }
};
