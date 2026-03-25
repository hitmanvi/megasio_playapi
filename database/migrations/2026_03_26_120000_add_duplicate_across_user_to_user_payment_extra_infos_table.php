<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 行级标记：该用户该支付方式该类型下，至少有一个配置为 unique 的字段值与其他用户重复
     */
    public function up(): void
    {
        Schema::table('user_payment_extra_infos', function (Blueprint $table) {
            $table->boolean('duplicate_across_user')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_payment_extra_infos', function (Blueprint $table) {
            $table->dropColumn('duplicate_across_user');
        });
    }
};
