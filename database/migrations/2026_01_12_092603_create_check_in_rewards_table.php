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
        Schema::create('check_in_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('day')->unique()->comment('签到天数（第几天）');
            $table->json('rewards')->comment('奖励列表 [{type, amount, description}]');
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_in_rewards');
    }
};
