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
        Schema::table('brands', function (Blueprint $table) {
            $table->tinyInteger('maintain_week_day')->nullable()->comment('维护星期几 (按PHP date("w")规律: 0=周日, 1=周一, 2=周二, 3=周三, 4=周四, 5=周五, 6=周六)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('maintain_week_day');
        });
    }
};
