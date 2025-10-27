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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment('Banner类型，如home、promotion等');
            $table->string('web_img_url')->nullable()->comment('Web端图片URL');
            $table->string('app_img_url')->nullable()->comment('App端图片URL');
            $table->string('web_rule_url')->nullable()->comment('Web端跳转URL');
            $table->string('app_rule_url')->nullable()->comment('App端跳转URL');
            $table->boolean('enabled')->default(false)->comment('是否启用');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->timestamp('started_at')->nullable()->comment('开始时间');
            $table->timestamp('ended_at')->nullable()->comment('结束时间');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();
            
            // 添加索引
            $table->index('type');
            $table->index('enabled');
            $table->index('sort_id');
            $table->index(['type', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
