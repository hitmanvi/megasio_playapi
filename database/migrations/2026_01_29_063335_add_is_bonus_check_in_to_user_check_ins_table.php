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
            $table->boolean('is_bonus_check_in')->default(false)->after('reward_day')->comment('是否为额外签到（基于充值通道）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_check_ins', function (Blueprint $table) {
            $table->dropColumn('is_bonus_check_in');
        });
    }
};
