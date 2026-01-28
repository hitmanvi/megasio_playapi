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
            // 修改 related_id 字段类型为字符串
            $table->string('related_id', 255)->nullable()->change();
            
            // 添加 source_type 和 related_id 的唯一索引
            $table->unique(['source_type', 'related_id'], 'invitation_rewards_source_type_related_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_rewards', function (Blueprint $table) {
            // 删除唯一索引
            $table->dropUnique('invitation_rewards_source_type_related_id_unique');
            
            // 恢复 related_id 字段类型为 unsignedBigInteger
            $table->unsignedBigInteger('related_id')->nullable()->change();
        });
    }
};
