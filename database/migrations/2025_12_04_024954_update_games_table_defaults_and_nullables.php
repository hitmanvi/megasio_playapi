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
        Schema::table('games', function (Blueprint $table) {
            // sort_id 设置默认值为 0（如果还没有默认值）
            $table->unsignedInteger('sort_id')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // 移除 sort_id 的默认值（恢复为无默认值）
            $table->unsignedInteger('sort_id')->default(null)->change();
        });
    }
};
