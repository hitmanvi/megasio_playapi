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
        Schema::table('invitation_rewards', function (Blueprint $table) {
            $table->decimal('wager', 20, 8)->default(0)->after('reward_amount')->comment('下注金额（当 source_type 为 bet 时使用）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_rewards', function (Blueprint $table) {
            $table->dropColumn('wager');
        });
    }
};
