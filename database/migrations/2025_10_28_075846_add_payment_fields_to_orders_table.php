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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_currency')->nullable()->after('currency')->comment('支付货币类型');
            $table->decimal('payment_amount', 20, 8)->nullable()->after('payment_currency')->comment('支付金额');
            $table->decimal('payment_payout', 20, 8)->nullable()->after('payment_amount')->comment('支付派彩金额');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_currency', 'payment_amount', 'payment_payout']);
        });
    }
};
