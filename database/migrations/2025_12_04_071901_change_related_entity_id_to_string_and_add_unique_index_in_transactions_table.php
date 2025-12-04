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
        Schema::table('transactions', function (Blueprint $table) {
            // 删除原来的 related_entity_id 索引
            $table->dropIndex(['related_entity_id']);
            
            // 将 related_entity_id 从 unsignedBigInteger 改为 string
            $table->string('related_entity_id')->nullable()->change();
            
            // 添加 type 和 related_entity_id 的联合唯一索引
            $table->unique(['type', 'related_entity_id'], 'transactions_type_related_entity_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // 删除联合唯一索引
            $table->dropUnique('transactions_type_related_entity_id_unique');
            
            // 将 related_entity_id 改回 unsignedBigInteger
            $table->unsignedBigInteger('related_entity_id')->nullable()->change();
            
            // 恢复原来的索引
            $table->index('related_entity_id');
        });
    }
};
