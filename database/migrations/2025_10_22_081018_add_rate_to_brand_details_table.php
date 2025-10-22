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
        Schema::table('brand_details', function (Blueprint $table) {
            $table->decimal('rate', 10, 4)->nullable()->after('game_count')->comment('汇率');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_details', function (Blueprint $table) {
            $table->dropColumn('rate');
        });
    }
};
