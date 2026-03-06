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
        if (!Schema::hasTable('agents')) {
            return;
        }

        if (Schema::hasColumn('agents', 'promotion_code')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropUnique(['promotion_code']);
                $table->dropIndex(['promotion_code']);
            });
            Schema::table('agents', fn (Blueprint $t) => $t->dropColumn('promotion_code'));
        }
        Schema::table('agents', function (Blueprint $table) {
            if (Schema::hasColumn('agents', 'facebook_config')) {
                $table->dropColumn('facebook_config');
            }
            if (Schema::hasColumn('agents', 'kochava_config')) {
                $table->dropColumn('kochava_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('agents')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            if (!Schema::hasColumn('agents', 'promotion_code')) {
                $table->string('promotion_code', 32)->nullable();
            }
            if (!Schema::hasColumn('agents', 'facebook_config')) {
                $table->json('facebook_config')->nullable();
            }
            if (!Schema::hasColumn('agents', 'kochava_config')) {
                $table->json('kochava_config')->nullable();
            }
        });
    }
};
