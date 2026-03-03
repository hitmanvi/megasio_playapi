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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Agent 名称');
            $table->string('promotion_code', 32)->unique()->comment('推广码');
            $table->string('facebook_pixel_id')->nullable()->comment('Facebook Pixel ID');
            $table->text('facebook_access_token')->nullable()->comment('Facebook Conversions API Access Token');
            $table->string('kochava_app_id')->nullable()->comment('Kochava App ID');
            $table->string('status', 20)->default('active')->comment('状态：active|inactive');
            $table->timestamps();

            $table->index('promotion_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
