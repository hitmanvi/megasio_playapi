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
        Schema::create('vip_level_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('组名称，如：Bronze, Silver, Gold');
            $table->string('icon')->nullable()->comment('组图标');
            $table->string('card_img')->nullable()->comment('组卡片图片');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index('sort_id');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_level_groups');
    }
};
