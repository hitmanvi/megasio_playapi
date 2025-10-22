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
            $table->time('maintain_start')->nullable()->comment('维护开始时间')->change();
            $table->time('maintain_end')->nullable()->comment('维护结束时间')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->timestamp('maintain_start')->nullable()->comment('维护开始时间')->change();
            $table->timestamp('maintain_end')->nullable()->comment('维护结束时间')->change();
        });
    }
};
