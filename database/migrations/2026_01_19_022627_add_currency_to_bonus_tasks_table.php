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
        Schema::table('bonus_tasks', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('status')->comment('币种');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonus_tasks', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
