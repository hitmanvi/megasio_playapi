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
        Schema::create('airdrops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')
                ->comment('用户 ID，每人一条');
            $table->decimal('amount', 20, 8)
                ->comment('空投数量');
            $table->string('currency', 16)
                ->comment('币种');
            $table->boolean('create_rollover')
                ->default(false)
                ->comment('是否创建 rollover');
            $table->text('remark')
                ->nullable()
                ->comment('备注');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airdrops');
    }
};
