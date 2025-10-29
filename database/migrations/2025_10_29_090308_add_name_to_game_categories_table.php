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
        Schema::table('game_categories', function (Blueprint $table) {
            $table->string('name')->after('id')->comment('默认名称（英文）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_categories', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
