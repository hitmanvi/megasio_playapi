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
        Schema::create('user_statistic_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('attribute_key', 100)->comment('属性键');
            $table->text('attribute_value')->nullable()->comment('属性值');
            $table->string('value_type', 20)->default('string')->comment('值类型：string, integer, decimal, boolean, json');
            $table->timestamps();

            // 添加索引
            $table->index('user_id');
            $table->index('attribute_key');
            $table->unique(['user_id', 'attribute_key'], 'user_statistic_attr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_statistic_attributes');
    }
};
