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
        Schema::create('customer_io_campaign_promotion_codes', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64)->comment('Customer.io Campaign ID');
            $table->unsignedBigInteger('promotion_code_id')->comment('promotion_codes.id');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->unique(['campaign_id', 'promotion_code_id'], 'cio_camp_promo_campaign_pc_uniq');
            $table->index('promotion_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_io_campaign_promotion_codes');
    }
};
