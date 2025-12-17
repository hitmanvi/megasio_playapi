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
        Schema::create('bundle_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->unique();            // 订单号
            $table->unsignedBigInteger('user_id')->index();      // 用户ID
            $table->unsignedBigInteger('bundle_id')->index();    // Bundle ID
            $table->unsignedBigInteger('payment_method_id')->index(); // 支付方式ID
            $table->decimal('gold_coin', 20, 8)->default(0);     // 获得的GoldCoin
            $table->decimal('social_coin', 20, 8)->default(0);   // 获得的SocialCoin
            $table->decimal('amount', 20, 8);                    // 支付金额
            $table->string('currency', 10);                      // 支付货币
            $table->string('out_trade_no', 128)->nullable()->index(); // 外部交易号
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('pay_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->string('user_ip', 64)->nullable();           // 用户IP
            $table->json('payment_info')->nullable();            // 支付信息
            $table->text('notes')->nullable();                   // 备注
            $table->timestamp('paid_at')->nullable();            // 支付时间
            $table->timestamp('finished_at')->nullable();        // 完成时间
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bundle_id')->references('id')->on('bundles')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_purchases');
    }
};
