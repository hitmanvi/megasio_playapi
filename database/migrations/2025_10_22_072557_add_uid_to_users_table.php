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
        Schema::table('users', function (Blueprint $table) {
            $table->string('uid')->nullable()->after('id')->comment('用户唯一标识');
        });

        // 为现有用户生成uid
        $users = \App\Models\User::whereNull('uid')->get();
        foreach ($users as $user) {
            $user->update(['uid' => \App\Models\User::generateUid()]);
        }

        // 设置uid为NOT NULL和UNIQUE
        Schema::table('users', function (Blueprint $table) {
            $table->string('uid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
