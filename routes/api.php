<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WithdrawController;
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
    Route::post('/send-verification-code', [AuthController::class, 'sendVerificationCode']);
    
    // 需要认证的路由
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/mine', [AuthController::class, 'mine']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/jwt-token', [AuthController::class, 'generateJwtToken']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Banner相关路由（只读）
Route::get('/banners', [BannerController::class, 'index']);

// 余额相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('balances')->group(function () {
    Route::get('/', [BalanceController::class, 'index']);
    Route::get('/{currency}', [BalanceController::class, 'show']);
    Route::get('/transactions/list', [BalanceController::class, 'transactions']);
    Route::post('/display-currencies', [BalanceController::class, 'setDisplayCurrencies']);
    Route::get('/display-currencies', [BalanceController::class, 'getDisplayCurrencies']);
});

// 存款相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('deposits')->group(function () {
    Route::get('/', [DepositController::class, 'index']);
    Route::post('/', [DepositController::class, 'store']);
    Route::get('/form-fields', [DepositController::class, 'formFields']);
    Route::get('/{orderNo}', [DepositController::class, 'show']);
});

// 提款相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('withdraws')->group(function () {
    Route::get('/', [WithdrawController::class, 'index']);
    Route::post('/', [WithdrawController::class, 'store']);
    Route::get('/form-fields', [WithdrawController::class, 'formFields']);
    Route::get('/{orderNo}', [WithdrawController::class, 'show']);
});

// 交易记录相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);
});

// 订单相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
});

// 邀请相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('invitations')->group(function () {
    Route::get('/stats', [InvitationController::class, 'stats']);
    Route::get('/', [InvitationController::class, 'index']);
});

// 品牌相关路由（只读）
Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']);
    Route::get('/recommend', [BrandController::class, 'recommend']);
    Route::get('/{id}', [BrandController::class, 'show']);
});

// 游戏分类相关路由（只读）
Route::get('/game-categories', [GameCategoryController::class, 'index']);

// 主题相关路由（只读）
Route::prefix('themes')->group(function () {
    Route::get('/', [ThemeController::class, 'index']);
    Route::get('/{id}', [ThemeController::class, 'show']);
});

// 支付方式相关路由（只读）
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

// 货币相关路由（只读）
Route::get('/currencies', [CurrencyController::class, 'index']);

// 游戏相关路由（只读）
Route::prefix('games')->group(function () {
    Route::get('/', [GameController::class, 'index']);
    Route::get('/recommend', [GameController::class, 'recommend']);
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
