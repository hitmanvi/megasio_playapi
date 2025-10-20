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
        Schema::create('brand_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->string('coin')->nullable()->comment('货币类型');
            $table->boolean('support')->default(false)->comment('是否支持');
            $table->boolean('configured')->default(false)->comment('是否已配置');
            $table->integer('game_count')->default(0)->comment('游戏数量');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamps();
            
            // 添加索引
            $table->index('brand_id');
            $table->index('coin');
            $table->index('enabled');
            $table->index('support');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_details');
    }
};
