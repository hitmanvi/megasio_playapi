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
            // 删除旧的唯一索引（只包含 source_type 和 related_id）
            $table->dropUnique('invitation_rewards_source_type_related_id_unique');
            
            // 添加新的唯一索引（包含 invitation_id, source_type 和 related_id）
            // 这样不同的 invitation 可以各自创建相同类型的奖励，但同一个 invitation 不能重复创建
            $table->unique(['invitation_id', 'source_type', 'related_id'], 'invitation_rewards_invitation_source_related_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_rewards', function (Blueprint $table) {
            // 删除新的唯一索引
            $table->dropUnique('invitation_rewards_invitation_source_related_unique');
            
            // 恢复旧的唯一索引
            $table->unique(['source_type', 'related_id'], 'invitation_rewards_source_type_related_id_unique');
        });
    }
};
