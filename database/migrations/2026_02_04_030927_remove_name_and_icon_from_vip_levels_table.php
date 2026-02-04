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
        Schema::table('vip_levels', function (Blueprint $table) {
            $table->dropColumn(['name', 'icon']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vip_levels', function (Blueprint $table) {
            $table->string('name')->after('level')->comment('等级名称');
            $table->string('icon')->nullable()->after('name')->comment('等级图标');
        });
    }
};
