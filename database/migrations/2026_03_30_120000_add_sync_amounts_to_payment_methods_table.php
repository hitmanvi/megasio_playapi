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
            $table->decimal('sync_min_amount', 20, 8)
                ->nullable()
                ->after('synced_at')
                ->comment('第三方同步的最小金额');
            $table->decimal('sync_max_amount', 20, 8)
                ->nullable()
                ->after('sync_min_amount')
                ->comment('第三方同步的最大金额');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['sync_min_amount', 'sync_max_amount']);
        });
    }
};
