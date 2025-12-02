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
        Schema::create('game_provider_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('provider', 50)->comment('游戏提供商标识');
            $table->string('currency', 10)->comment('货币类型');
            $table->string('token', 255)->unique()->comment('Token值');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();
            
            // 添加索引
            $table->index(['user_id', 'provider', 'currency']);
            $table->index('token');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_provider_tokens');
    }
};
