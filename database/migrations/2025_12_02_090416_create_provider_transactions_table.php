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
        Schema::create('provider_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->unsignedBigInteger('game_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('txid', 100)->index();
            $table->string('round_id', 100)->index();
            $table->json('detail')->nullable();
            $table->timestamps();
            
            // 唯一索引：provider + txid 组合唯一
            $table->unique(['provider', 'txid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_transactions');
    }
};
