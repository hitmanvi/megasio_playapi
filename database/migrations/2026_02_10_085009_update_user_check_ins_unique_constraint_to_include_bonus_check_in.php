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
        Schema::table('user_check_ins', function (Blueprint $table) {
            // 删除旧的唯一约束
            $table->dropUnique(['user_id', 'check_in_date']);
            
            // 添加新的唯一约束，包含 is_bonus_check_in
            // 这样同一天可以有普通签到和 bonus 签到两条记录
            $table->unique(['user_id', 'check_in_date', 'is_bonus_check_in'], 'user_check_ins_user_date_bonus_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_check_ins', function (Blueprint $table) {
            // 删除新的唯一约束
            $table->dropUnique('user_check_ins_user_date_bonus_unique');
            
            // 恢复旧的唯一约束
            $table->unique(['user_id', 'check_in_date']);
        });
    }
};
