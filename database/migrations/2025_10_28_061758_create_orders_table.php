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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('game_id')->comment('游戏ID');
            $table->unsignedBigInteger('brand_id')->comment('品牌ID');
            $table->decimal('amount', 20, 8)->comment('订单金额');
            $table->decimal('payout', 20, 8)->nullable()->default(0)->comment('派彩金额');
            $table->string('status')->comment('订单状态');
            $table->string('currency')->comment('货币类型');
            $table->text('notes')->nullable()->comment('备注信息');
            $table->timestamp('finished_at')->nullable()->comment('完成时间');
            $table->string('order_id')->unique()->comment('系统订单ID');
            $table->string('out_id')->nullable()->comment('外部订单ID');
            $table->integer('version')->default(0)->comment('乐观锁版本号');
            $table->timestamps();
            
            // 添加索引
            $table->index('user_id');
            $table->index('game_id');
            $table->index('brand_id');
            $table->index('status');
            $table->index('currency');
            $table->index('order_id');
            $table->index('out_id');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['brand_id', 'status']);
            $table->index(['status', 'finished_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
