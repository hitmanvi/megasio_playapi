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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id')->comment('邀请人ID');
            $table->unsignedBigInteger('invitee_id')->unique()->comment('被邀请人ID');
            $table->timestamps();

            // 添加索引
            $table->index('inviter_id');
            $table->index('invitee_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
