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
        Schema::create('vip_levels', function (Blueprint $table) {
            $table->id();
            $table->string('level')->unique()->comment('等级标识');
            $table->string('name')->comment('等级名称');
            $table->string('icon')->nullable()->comment('等级图标');
            $table->integer('required_exp')->default(0)->comment('所需经验值');
            $table->text('description')->nullable()->comment('等级说明');
            $table->json('benefits')->nullable()->comment('等级权益');
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
        Schema::dropIfExists('vip_levels');
    }
};
