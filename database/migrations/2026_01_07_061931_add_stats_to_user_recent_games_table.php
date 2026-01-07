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
        Schema::table('user_recent_games', function (Blueprint $table) {
            $table->integer('play_count')->default(0)->after('last_played_at')->comment('游玩次数');
            $table->decimal('max_multiplier', 10, 2)->default(0)->after('play_count')->comment('最大奖励倍数');
            
            $table->index('play_count');
            $table->index('max_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_recent_games', function (Blueprint $table) {
            $table->dropIndex(['play_count']);
            $table->dropIndex(['max_multiplier']);
            $table->dropColumn(['play_count', 'max_multiplier']);
        });
    }
};
