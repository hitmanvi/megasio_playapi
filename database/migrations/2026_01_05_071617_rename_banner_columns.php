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
        Schema::table('banners', function (Blueprint $table) {
            $table->renameColumn('web_img_url', 'web_img');
            $table->renameColumn('app_img_url', 'app_img');
            $table->renameColumn('web_rule_url', 'web_href');
            $table->renameColumn('app_rule_url', 'app_href');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->renameColumn('web_img', 'web_img_url');
            $table->renameColumn('app_img', 'app_img_url');
            $table->renameColumn('web_href', 'web_rule_url');
            $table->renameColumn('app_href', 'app_rule_url');
        });
    }
};
