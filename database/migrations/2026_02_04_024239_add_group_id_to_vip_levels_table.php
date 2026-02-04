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
            $table->unsignedBigInteger('group_id')->nullable()->after('id')->comment('VIP等级组ID');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vip_levels', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
