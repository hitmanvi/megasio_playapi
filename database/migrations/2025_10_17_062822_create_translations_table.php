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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type'); // 模型类名，如 App\Models\Tag
            $table->unsignedBigInteger('translatable_id'); // 模型ID
            $table->string('field'); // 字段名，如 name, description
            $table->string('locale', 10); // 语言代码，如 en, zh-CN, ja
            $table->text('value'); // 翻译内容
            $table->timestamps();
            
            // 创建复合索引
            $table->index(['translatable_type', 'translatable_id', 'field', 'locale'], 'translation_lookup');
            $table->unique(['translatable_type', 'translatable_id', 'field', 'locale'], 'translation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};