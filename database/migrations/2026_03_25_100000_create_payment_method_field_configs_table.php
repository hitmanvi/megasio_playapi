<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 支付方式字段配置（与 payment_methods.name 对应；deposit/withdraw 各一套字段定义）
     *
     * deposit_fields / withdraw_fields 示例：
     * [{"key":"email","unique":true},{"key":"account_name","unique":false}]
     */
    public function up(): void
    {
        Schema::create('payment_method_field_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('payment_methods.name');
            $table->json('deposit_fields')->nullable()->comment('充值表单字段定义');
            $table->json('withdraw_fields')->nullable()->comment('提现表单字段定义');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_field_configs');
    }
};
