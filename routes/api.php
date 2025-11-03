<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\GameCategoryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameGroupController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TranslationExampleController;
use App\Http\Controllers\UtilsController;
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

// 余额相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('balances')->group(function () {
    Route::get('/', [BalanceController::class, 'index']);
    Route::get('/{currency}', [BalanceController::class, 'show']);
    Route::get('/transactions/list', [BalanceController::class, 'transactions']);
});

// 品牌相关路由（只读）
Route::get('/brands', [BrandController::class, 'index']);

// 游戏分类相关路由（只读）
Route::get('/game-categories', [GameCategoryController::class, 'index']);

// 主题相关路由（只读）
Route::get('/themes', [ThemeController::class, 'index']);

// 支付方式相关路由（只读）
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

// 游戏相关路由（只读）
Route::prefix('games')->group(function () {
    Route::get('/', [GameController::class, 'index']);
    Route::get('/{id}', [GameController::class, 'show']);
});

// 游戏群组相关路由（只读）
Route::prefix('game-groups')->group(function () {
    Route::get('/', [GameGroupController::class, 'index']);
    Route::get('/category/{category}', [GameGroupController::class, 'getByCategory']);
    Route::get('/{id}', [GameGroupController::class, 'show']);
    Route::get('/{groupId}/games', [GameGroupController::class, 'getGames']);
});

// 工具类路由
Route::get('/timestamp', [UtilsController::class, 'timestamp']);
Route::get('/settings', [UtilsController::class, 'settings']);
