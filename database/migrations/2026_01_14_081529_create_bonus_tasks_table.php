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
        Schema::create('bonus_tasks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->string('task_no', 16)->nullable()->index();
            $table->string('bonus_name', 50)->nullable();
            $table->decimal('cap_bonus', 20, 4)->unsigned()->default(0);
            $table->decimal('base_bonus', 20, 4)->unsigned()->default(0);
            $table->decimal('last_bonus', 20, 4)->unsigned()->default(0);
            $table->decimal('need_wager', 20, 4)->unsigned()->default(0);
            $table->decimal('wager', 20, 4)->unsigned()->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_tasks');
    }
};
