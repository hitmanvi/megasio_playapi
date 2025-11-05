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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('order_no')->unique()->comment('订单号');
            $table->string('out_trade_no')->nullable()->comment('外部交易号');
            $table->string('currency', 10)->comment('货币类型');
            $table->decimal('amount', 20, 8)->comment('金额');
            $table->decimal('actual_amount', 20, 8)->nullable()->comment('实际到账金额');
            $table->unsignedBigInteger('payment_method_id')->comment('支付方式ID');
            $table->json('withdraw_info')->nullable()->comment('提款信息');
            $table->json('extra_info')->nullable()->comment('额外信息');
            $table->string('status')->comment('状态');
            $table->string('pay_status')->comment('支付状态');
            $table->decimal('fee', 20, 8)->nullable()->default(0)->comment('手续费');
            $table->boolean('approved')->default(false)->comment('是否通过审核');
            $table->string('user_ip')->nullable()->comment('用户IP');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            // 添加索引
            $table->index('user_id');
            $table->index('payment_method_id');
            $table->index('out_trade_no');
            $table->index('currency');
            $table->index('status');
            $table->index('pay_status');
            $table->index('approved');
            $table->index('completed_at');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'pay_status']);
            $table->index(['status', 'approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};
