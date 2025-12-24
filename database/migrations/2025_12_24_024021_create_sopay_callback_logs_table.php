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
        Schema::create('sopay_callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();         // Sopay 订单号
            $table->string('out_trade_no')->nullable()->index();     // 我方订单号
            $table->string('subject')->nullable();                   // 业务类型 (deposit/withdraw)
            $table->string('status')->nullable();                    // 回调状态
            $table->decimal('amount', 20, 8)->nullable();            // 金额
            $table->json('request_headers')->nullable();             // 请求头
            $table->json('request_body')->nullable();                // 请求体
            $table->text('sign_data')->nullable();                   // 签名数据
            $table->string('signature')->nullable();                 // 签名
            $table->boolean('signature_valid')->default(false);      // 签名是否有效
            $table->string('process_result')->nullable();            // 处理结果
            $table->text('process_error')->nullable();               // 处理错误信息
            $table->string('ip')->nullable();                        // 请求IP
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sopay_callback_logs');
    }
};
