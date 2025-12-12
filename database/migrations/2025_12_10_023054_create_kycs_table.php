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
        Schema::create('kycs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique(); // 一个用户只能有一个KYC
            $table->string('name')->nullable();
            $table->string('birthdate')->nullable();
            $table->string('document_front')->nullable();
            $table->string('document_back')->nullable();
            $table->string('document_number')->nullable();
            $table->string('selfie')->nullable();
            
            // 审核相关字段
            // pending: 初审待审核, approved: 初审通过, rejected: 初审拒绝
            // advanced_pending: 高级认证待审核, advanced_approved: 高级认证通过(完成), advanced_rejected: 高级认证拒绝
            $table->enum('status', ['pending', 'approved', 'rejected', 'advanced_pending', 'advanced_approved', 'advanced_rejected'])->default('pending');
            $table->text('reject_reason')->nullable(); // 拒绝原因
            
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kycs');
    }
};
