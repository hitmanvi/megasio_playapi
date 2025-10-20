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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider');
            $table->json('restricted_region')->nullable()->comment('限制地区列表');
            $table->integer('sort_id')->default(0)->comment('排序ID');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamp('maintain_start')->nullable()->comment('维护开始时间');
            $table->timestamp('maintain_end')->nullable()->comment('维护结束时间');
            $table->boolean('maintain_auto')->default(false)->comment('是否自动维护');
            $table->timestamps();
            
            // 添加索引
            $table->index('provider');
            $table->index('enabled');
            $table->index('sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
