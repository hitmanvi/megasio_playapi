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
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->integer('sort_id')->default(0)->comment('排序ID')->after('enabled');
            $table->index('sort_id');
            $table->index(['enabled', 'type', 'sort_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['payment_methods_enabled_type_sort_id_index']);
            $table->dropIndex(['payment_methods_sort_id_index']);
            $table->dropColumn('sort_id');
        });
    }
};
