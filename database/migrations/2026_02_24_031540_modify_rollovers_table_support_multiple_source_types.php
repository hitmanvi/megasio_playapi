<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rollovers', function (Blueprint $table) {
            // 删除旧的外键约束
            $table->dropForeign(['deposit_id']);
        });

        // 使用 DB 语句重命名列（因为 Laravel Schema Builder 可能不支持 renameColumn）
        DB::statement('ALTER TABLE rollovers CHANGE deposit_id related_id BIGINT UNSIGNED NOT NULL COMMENT \'关联的订单ID（根据 source_type 关联不同类型的记录）\'');
        DB::statement('ALTER TABLE rollovers CHANGE deposit_amount amount DECIMAL(20, 8) NOT NULL COMMENT \'金额（根据 source_type 可能是充值金额、奖励金额等）\'');

        Schema::table('rollovers', function (Blueprint $table) {
            // 添加 source_type 字段（来源类型：deposit, bonus, reward 等）
            $table->string('source_type', 50)->default('deposit')->after('user_id')->comment('来源类型：deposit（充值）、bonus（奖励）、reward（奖励）等');
            
            // 添加索引
            $table->index('source_type');
            $table->index(['source_type', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rollovers', function (Blueprint $table) {
            // 删除新添加的索引
            $table->dropIndex(['source_type', 'related_id']);
            $table->dropIndex(['source_type']);
            
            // 删除 source_type 字段
            $table->dropColumn('source_type');
        });

        // 使用 DB 语句恢复列名
        DB::statement('ALTER TABLE rollovers CHANGE related_id deposit_id BIGINT UNSIGNED NOT NULL COMMENT \'关联的充值订单ID\'');
        DB::statement('ALTER TABLE rollovers CHANGE amount deposit_amount DECIMAL(20, 8) NOT NULL COMMENT \'充值金额\'');

        Schema::table('rollovers', function (Blueprint $table) {
            // 恢复外键约束
            $table->foreign('deposit_id')->references('id')->on('deposits')->onDelete('cascade');
        });
    }
};
