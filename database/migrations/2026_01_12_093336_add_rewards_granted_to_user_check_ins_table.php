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
            $table->unsignedInteger('reward_day')->nullable()->after('consecutive_days')->comment('奖励档位');
            $table->json('rewards_granted')->nullable()->after('reward_day')->comment('发放的奖励');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_check_ins', function (Blueprint $table) {
            $table->dropColumn(['reward_day', 'rewards_granted']);
        });
    }
};
