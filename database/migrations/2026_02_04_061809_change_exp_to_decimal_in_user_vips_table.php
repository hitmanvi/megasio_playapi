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
        Schema::table('user_vips', function (Blueprint $table) {
            $table->decimal('exp', 10, 4)->default(0)->change()->comment('VIP经验值');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_vips', function (Blueprint $table) {
            $table->integer('exp')->default(0)->change()->comment('VIP经验值');
        });
    }
};
