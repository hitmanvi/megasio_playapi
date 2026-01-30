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
        Schema::create('rollovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('deposit_id')->comment('关联的充值订单ID');
            $table->string('currency')->comment('货币类型');
            $table->decimal('deposit_amount', 20, 8)->comment('充值金额');
            $table->decimal('required_wager', 20, 8)->comment('需要的流水（充值金额的1倍）');
            $table->decimal('current_wager', 20, 8)->default(0)->comment('当前已完成的流水');
            $table->string('status')->default('pending')->comment('状态：pending, active, completed');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();

            // 添加索引
            $table->index('user_id');
            $table->index('deposit_id');
            $table->index('status');
            $table->index('currency');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('deposit_id')->references('id')->on('deposits')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rollovers');
    }
};
