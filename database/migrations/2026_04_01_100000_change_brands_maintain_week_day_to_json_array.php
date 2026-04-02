<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'maintain_week_day')) {
                $table->dropColumn('maintain_week_day');
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->json('maintain_week_day')->nullable()->after('maintain_auto')
                ->comment('维护星期（PHP date("w"): 0=周日…6=周六），可多选；空或 null 表示不限制星期');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'maintain_week_day')) {
                $table->dropColumn('maintain_week_day');
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->tinyInteger('maintain_week_day')->nullable()->after('maintain_auto')
                ->comment('维护星期几 (按PHP date("w")规律: 0=周日, 1=周一, …)');
        });
    }
};
