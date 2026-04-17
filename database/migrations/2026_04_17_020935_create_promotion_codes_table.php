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
        Schema::create('promotion_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('展示名称');
            $table->string('code')->unique()->comment('兑换码（唯一）');
            $table->unsignedInteger('times')->comment('全码可领取总次数（用尽后不可再领）');
            $table->unsignedInteger('claimed_count')->default(0)->comment('已成功领取次数（与 claims 表 completed 同步）');
            $table->string('bonus_type', 32)->default('bonus_task')->comment('奖励类型，暂仅 bonus_task');
            $table->json('bonus_config')->comment('奖励参数（结构依 bonus_type 而定）');
            $table->timestamp('expired_at')->nullable()->comment('兑换码失效时间，null 表示永不过期');
            $table->string('target_type', 32)->comment('领取对象标识（如 all、users 等，由业务解释）');
            $table->string('status', 32)->default('active')->comment('active 可领 / inactive 停用 / exhausted 已领完');
            $table->timestamps();

            $table->index('bonus_type');
            $table->index('target_type');
            $table->index('status');
            $table->index('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_codes');
    }
};
