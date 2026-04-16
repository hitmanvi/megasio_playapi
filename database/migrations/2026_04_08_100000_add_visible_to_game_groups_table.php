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
        Schema::table('game_groups', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('enabled')->comment('是否在列表等场景展示');
            $table->index('visible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_groups', function (Blueprint $table) {
            $table->dropIndex(['visible']);
            $table->dropColumn('visible');
        });
    }
};
