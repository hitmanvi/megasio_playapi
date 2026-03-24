<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用户充提扩展信息：payment_methods.name + type(deposit|withdraw) 区分充提，data 为 extra_info 各 key 的值与只读标记
     *
     * data 结构示例：
     * { "field_key": { "value": "", "read_only": false } }
     */
    public function up(): void
    {
        Schema::create('user_payment_extra_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('users.id');
            $table->index('user_id');
            $table->string('name')->comment('payment_methods.name');
            $table->string('type', 20)->comment('deposit|withdraw');
            $table->json('data')->comment('extra_info key -> { value, read_only }');
            $table->timestamps();

            $table->unique(['user_id', 'name', 'type']);
            $table->index('name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payment_extra_infos');
    }
};
