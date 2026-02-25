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
        Schema::create('weekly_cashbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('period')->comment('周期：ISO 年*100+周数，如 202605 表示 2026 年第 5 周');
            $table->string('currency', 10)->comment('货币');
            $table->decimal('wager', 20, 8)->default(0)->comment('周期内投注额');
            $table->decimal('payout', 20, 8)->default(0)->comment('周期内派彩');
            $table->string('status', 20)->default('active')->comment('状态：active 进行中, claimable 待领取, claimed 已领取, expired 已过期');
            $table->decimal('rate', 8, 4)->default(0)->comment('返现比例');
            $table->decimal('amount', 20, 8)->default(0)->comment('返现金额');
            $table->timestamp('claimed_at')->nullable()->comment('领取时间');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('period');
            $table->index('currency');
            $table->unique(['user_id', 'period', 'currency'], 'weekly_cashbacks_user_period_currency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_cashbacks');
    }
};
