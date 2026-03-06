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
        Schema::create('agent_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->comment('Agent ID');
            $table->string('name')->comment('链接名称');
            $table->string('promotion_code', 32)->unique()->comment('推广码');
            $table->string('status', 20)->default('active')->comment('状态：active|inactive');
            $table->json('facebook_config')->nullable()->comment('Facebook Conversions 配置');
            $table->json('kochava_config')->nullable()->comment('Kochava 配置');
            $table->timestamps();

            $table->index('agent_id');
            $table->index('promotion_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_links');
    }
};
