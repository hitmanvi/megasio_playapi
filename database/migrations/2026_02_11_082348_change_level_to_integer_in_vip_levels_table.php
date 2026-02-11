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
        Schema::table('vip_levels', function (Blueprint $table) {
            // 先删除唯一约束
            $table->dropUnique(['level']);
        });

        // 将 level 字段从 string 转换为 integer
        // 注意：如果数据库中已有数据，需要确保 level 值可以转换为整数
        DB::statement('ALTER TABLE vip_levels MODIFY COLUMN level INTEGER NOT NULL');

        Schema::table('vip_levels', function (Blueprint $table) {
            // 重新添加唯一约束
            $table->unique('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vip_levels', function (Blueprint $table) {
            // 先删除唯一约束
            $table->dropUnique(['level']);
        });

        // 将 level 字段从 integer 转换回 string
        DB::statement('ALTER TABLE vip_levels MODIFY COLUMN level VARCHAR(255) NOT NULL');

        Schema::table('vip_levels', function (Blueprint $table) {
            // 重新添加唯一约束
            $table->unique('level');
        });
    }
};
