<?php

use App\Http\Controllers\TranslationExampleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 多语言标签相关路由
Route::prefix('tags')->group(function () {
    // 获取所有标签列表
    Route::get('/', [TranslationExampleController::class, 'getTagsList']);
    
    // 根据类型获取标签
    Route::get('/type/{type}', [TranslationExampleController::class, 'getTagsByType']);
    
    // 搜索标签
    Route::get('/search', [TranslationExampleController::class, 'searchTags']);
    
    // 获取单个标签详情
    Route::get('/{id}', [TranslationExampleController::class, 'getTagWithTranslations']);
    
    // 创建标签
    Route::post('/', [TranslationExampleController::class, 'createTag']);
});

// 演示路由
Route::get('/translation-example', [TranslationExampleController::class, 'example']);
