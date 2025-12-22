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
        Schema::dropIfExists('tags');
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();              // 标签名称（唯一标识）
            $table->string('display_name')->nullable();    // 显示名称
            $table->string('color')->nullable();           // 颜色 (如 #FF5733)
            $table->string('description')->nullable();     // 描述
            $table->boolean('enabled')->default(true);     // 是否启用
            $table->integer('sort_id')->default(0);        // 排序
            $table->timestamps();

            $table->index('enabled');
            $table->index('sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
