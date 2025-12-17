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
        Schema::create('redeems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('order_no', 50)->unique();
            $table->string('out_trade_no', 100)->nullable()->index();
            
            // SC 兑换信息
            $table->decimal('sc_amount', 20, 8)->comment('消耗的 SC 数量');
            $table->decimal('exchange_rate', 20, 8)->comment('兑换汇率 (SC -> USD)');
            $table->decimal('usd_amount', 20, 8)->comment('兑换得到的 USD 数量');
            
            // 提现信息
            $table->decimal('actual_amount', 20, 8)->nullable()->comment('实际到账金额');
            $table->decimal('fee', 20, 8)->default(0)->comment('手续费');
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->json('withdraw_info')->nullable()->comment('提现信息');
            $table->json('extra_info')->nullable()->comment('额外信息');
            
            // 状态
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('pay_status', 20)->default('PENDING')->index();
            $table->boolean('approved')->default(false)->index();
            
            // 其他信息
            $table->string('user_ip', 45)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redeems');
    }
};
