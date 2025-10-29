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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable()->comment('图标');
            $table->string('name')->comment('名称');
            $table->string('display_name')->comment('显示名称');
            $table->string('currency', 10)->comment('货币类型');
            $table->enum('type', ['deposit', 'withdraw'])->comment('类型：存款/提款');
            $table->json('amounts')->nullable()->comment('支持金额数组');
            $table->decimal('max_amount', 20, 8)->nullable()->comment('支持最大金额');
            $table->decimal('min_amount', 20, 8)->nullable()->comment('支持最小金额');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamp('synced_at')->nullable()->comment('同步时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
            
            // 添加索引
            $table->index('type');
            $table->index('currency');
            $table->index('enabled');
            $table->index(['type', 'currency']);
            $table->index(['enabled', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
