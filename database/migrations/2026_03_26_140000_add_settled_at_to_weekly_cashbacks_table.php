<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 周期结算时间（计算 rate/amount 并变为 claimable 或零额 claimed 的时刻） */
    public function up(): void
    {
        Schema::table('weekly_cashbacks', function (Blueprint $table) {
            $table->timestamp('settled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_cashbacks', function (Blueprint $table) {
            $table->dropColumn('settled_at');
        });
    }
};
