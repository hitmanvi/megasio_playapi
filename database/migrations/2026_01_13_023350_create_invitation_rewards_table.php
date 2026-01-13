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
        Schema::create('invitation_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('获得奖励的用户');
            $table->unsignedBigInteger('invitation_id')->index()->comment('关联的邀请记录');
            $table->string('source_type', 50)->comment('奖励来源类型：register, deposit, bet 等');
            $table->string('reward_type', 50)->comment('奖励类型（货币代码）');
            $table->decimal('reward_amount', 20, 8)->default(0)->comment('奖励数量');
            $table->unsignedBigInteger('related_id')->nullable()->comment('关联ID（如订单ID）');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_rewards');
    }
};
