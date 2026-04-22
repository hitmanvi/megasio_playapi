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
        Schema::table('promotion_codes', function (Blueprint $table) {
            $table->text('remark')->nullable()->after('status')->comment('备注（运营/内部说明）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_codes', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
