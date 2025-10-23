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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('currency')->comment('货币类型');
            $table->decimal('available', 20, 8)->default(0)->comment('可用余额');
            $table->decimal('frozen', 20, 8)->default(0)->comment('冻结余额');
            $table->integer('version')->default(0)->comment('乐观锁版本号');
            $table->timestamps();
            
            // 添加索引
            $table->index('user_id');
            $table->index('currency');
            $table->unique(['user_id', 'currency']); // 每个用户每种货币只能有一条记录
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
