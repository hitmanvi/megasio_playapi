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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID，null表示系统消息');
            $table->string('type', 20)->default('user')->comment('消息类型：system（系统消息）、user（用户消息）');
            $table->string('category', 50)->comment('消息分类：deposit_success（充值成功）、withdraw_success（提现成功）、vip_level_up（VIP等级提升）等');
            $table->string('title')->comment('消息标题');
            $table->text('content')->comment('消息内容');
            $table->json('data')->nullable()->comment('额外数据（JSON格式）');
            $table->timestamp('read_at')->nullable()->comment('阅读时间');
            $table->timestamps();
            
            // 添加索引
            $table->index('user_id');
            $table->index('type');
            $table->index('category');
            $table->index('read_at');
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
