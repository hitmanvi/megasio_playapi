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
        if (! Schema::hasTable('agent_links') || Schema::hasColumn('agent_links', 'tiktok_config')) {
            return;
        }

        Schema::table('agent_links', function (Blueprint $table) {
            $table->json('tiktok_config')
                ->nullable()
                ->after('kochava_config')
                ->comment('TikTok Events 配置 JSON');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('agent_links') || ! Schema::hasColumn('agent_links', 'tiktok_config')) {
            return;
        }

        Schema::table('agent_links', function (Blueprint $table) {
            $table->dropColumn('tiktok_config');
        });
    }
};
