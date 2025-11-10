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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('货币代码（ISO 4217）');
            $table->string('symbol', 10)->comment('货币符号');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->integer('sort_order')->default(0)->comment('排序顺序');
            $table->timestamps();
            
            // 添加索引
            $table->index('enabled');
            $table->index('sort_order');
            $table->index(['enabled', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
