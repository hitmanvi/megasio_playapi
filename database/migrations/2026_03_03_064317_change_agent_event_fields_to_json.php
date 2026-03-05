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
        if (!Schema::hasTable('agents') || !Schema::hasColumn('agents', 'facebook_pixel_id')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->json('facebook_config')->nullable()->after('promotion_code')->comment('Facebook Conversions 配置 JSON');
            $table->json('kochava_config')->nullable()->after('facebook_config')->comment('Kochava 配置 JSON');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['facebook_pixel_id', 'facebook_access_token', 'kochava_app_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('agents') || !Schema::hasColumn('agents', 'facebook_config')) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->string('facebook_pixel_id')->nullable()->after('promotion_code');
            $table->text('facebook_access_token')->nullable()->after('facebook_pixel_id');
            $table->string('kochava_app_id')->nullable()->after('facebook_access_token');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['facebook_config', 'kochava_config']);
        });
    }
};
