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
        if (Schema::hasColumn('agents', 'parent_id')) {
            Schema::table('agents', fn (Blueprint $table) => $table->dropColumn('parent_id'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', fn (Blueprint $table) => $table->unsignedBigInteger('parent_id')->nullable());
    }
};
