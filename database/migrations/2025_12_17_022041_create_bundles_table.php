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
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // Bundle名称
            $table->string('description')->nullable();           // 描述
            $table->string('icon')->nullable();                  // 图标
            $table->decimal('gold_coin', 20, 8)->default(0);     // 包含的GoldCoin数量
            $table->decimal('social_coin', 20, 8)->default(0);   // 包含的SocialCoin数量
            $table->decimal('original_price', 20, 8);            // 原始价格
            $table->decimal('discount_price', 20, 8)->nullable(); // 折扣价格
            $table->string('currency', 10)->default('USD');      // 支付货币
            $table->integer('stock')->nullable();                // 库存，null表示无限
            $table->boolean('enabled')->default(true);           // 是否启用
            $table->integer('sort_id')->default(0);              // 排序
            $table->timestamps();

            $table->index('enabled');
            $table->index('sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
