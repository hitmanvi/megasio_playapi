<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('remark')->nullable()->after('ban_reason')->comment('备注');
            $table->string('register_method', 20)->nullable()->after('remark')->comment('注册方式：phone|email|google');
            $table->timestamp('last_login_at')->nullable()->after('register_method')->comment('最后登录时间');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['remark', 'register_method', 'last_login_at']);
        });
    }
};
