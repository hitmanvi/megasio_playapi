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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('文章标题');
            $table->text('content')->nullable()->comment('文章内容');
            $table->unsignedBigInteger('group_id')->nullable()->comment('所属分组ID');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->timestamps();
            
            // 添加索引
            $table->index('group_id');
            $table->index('enabled');
            $table->index('sort_id');
            $table->index(['group_id', 'enabled', 'sort_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
