<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 将邀请充值奖励的 source_type 从统一的 deposit 拆为 deposit_starter / deposit_advanced
     */
    public function up(): void
    {
        DB::table('invitation_rewards')
            ->where('source_type', 'deposit')
            ->where('related_id', 'deposit_bonus_starter')
            ->update(['source_type' => 'deposit_starter']);

        DB::table('invitation_rewards')
            ->where('source_type', 'deposit')
            ->where('related_id', 'deposit_bonus_advanced')
            ->update(['source_type' => 'deposit_advanced']);
    }

    public function down(): void
    {
        DB::table('invitation_rewards')
            ->where('source_type', 'deposit_starter')
            ->where('related_id', 'deposit_bonus_starter')
            ->update(['source_type' => 'deposit']);

        DB::table('invitation_rewards')
            ->where('source_type', 'deposit_advanced')
            ->where('related_id', 'deposit_bonus_advanced')
            ->update(['source_type' => 'deposit']);
    }
};
