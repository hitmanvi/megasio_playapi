<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\TranslationExampleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 认证相关路由
Route::prefix('auth')->group(function () {
    // 公开路由
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // 需要认证的路由
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/mine', [AuthController::class, 'mine']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

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

// Banner相关路由（只读）
Route::get('/banners', [BannerController::class, 'index']);

// 演示路由
Route::get('/translation-example', [TranslationExampleController::class, 'example']);
