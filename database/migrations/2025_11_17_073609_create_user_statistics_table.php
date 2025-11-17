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
        Schema::create('user_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            $table->decimal('total_deposit', 20, 8)->default(0)->comment('充值总额');
            $table->decimal('total_withdraw', 20, 8)->default(0)->comment('提现总额');
            $table->decimal('total_order_amount', 20, 8)->default(0)->comment('下单总额');
            $table->decimal('total_payout', 20, 8)->default(0)->comment('派彩总额');
            $table->timestamps();

            // 添加索引
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_statistics');
    }
};
