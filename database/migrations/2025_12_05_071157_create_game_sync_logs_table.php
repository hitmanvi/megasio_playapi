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
        Schema::create('game_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->comment('提供商标识');
            $table->unsignedBigInteger('brand_id')->nullable()->comment('品牌ID');
            $table->integer('total_count')->default(0)->comment('提供商返回的游戏总数');
            $table->integer('available_count')->default(0)->comment('可用游戏数量');
            $table->integer('maintenance_count')->default(0)->comment('维护中游戏数量');
            $table->integer('deleted_count')->default(0)->comment('删除的游戏数量');
            $table->integer('created_count')->default(0)->comment('新建的游戏数量');
            $table->integer('updated_count')->default(0)->comment('更新的游戏数量');
            $table->string('status')->default('success')->comment('同步状态：success, failed');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->timestamp('started_at')->comment('开始时间');
            $table->timestamp('finished_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            // 添加索引
            $table->index('provider');
            $table->index('brand_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sync_logs');
    }
};
