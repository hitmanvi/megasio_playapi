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
        Schema::create('promotion_code_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('promotion_code_id');
            $table->string('status', 32)->default('pending')->comment('pending 处理中 / completed 已完成');
            $table->timestamp('claimed_at')->nullable()->comment('领取时间，null 表示尚未记录');
            $table->timestamp('expired_at')->nullable()->comment('领取记录失效时间，null 表示不过期（如 pending 需在此前完成）');
            $table->timestamps();

            $table->unique(['user_id', 'promotion_code_id']);
            $table->index('promotion_code_id');
            $table->index('status');
            $table->index('claimed_at');
            $table->index('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_code_claims');
    }
};
