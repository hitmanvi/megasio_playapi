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
        Schema::table('settings', function (Blueprint $table) {
            $table->integer('sort_id')->default(0)->comment('排序ID')->after('group');
            $table->index('sort_id');
            $table->index(['group', 'sort_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['group', 'sort_id']);
            $table->dropIndex(['sort_id']);
            $table->dropColumn('sort_id');
        });
    }
};
