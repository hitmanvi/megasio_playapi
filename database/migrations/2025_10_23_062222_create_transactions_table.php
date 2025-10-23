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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('交易涉及用户');
            $table->string('currency')->comment('货币类型');
            $table->decimal('amount', 20, 8)->comment('交易金额');
            $table->string('type')->comment('交易类型');
            $table->string('status')->comment('交易状态');
            $table->unsignedBigInteger('related_entity_id')->nullable()->comment('关联的业务ID');
            $table->text('notes')->nullable()->comment('交易附注');
            $table->timestamp('transaction_time')->comment('交易发生时间');
            $table->timestamps();
            
            // 添加索引
            $table->index('user_id');
            $table->index('currency');
            $table->index('type');
            $table->index('status');
            $table->index('transaction_time');
            $table->index('related_entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
