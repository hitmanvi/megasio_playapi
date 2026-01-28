<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WithdrawController;
use App\Http\Controllers\GameCategoryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameGroupController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UtilsController;
use App\Http\Controllers\GameProviders\FunkyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SopayController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\RedeemController;
use App\Http\Controllers\VipController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\BonusTaskController;
use App\Http\Controllers\ArticleGroupController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\NotificationController;

// 认证相关路由
Route::prefix('auth')->group(function () {
    // 公开路由
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google', [AuthController::class, 'loginWithGoogle']);
    Route::post('/send-verification-code', [AuthController::class, 'sendVerificationCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // 需要认证的路由
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/jwt-token', [AuthController::class, 'generateJwtToken']);
    });
});

// 用户相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/mine', [UserController::class, 'show']);
    Route::patch('/mine', [UserController::class, 'update']);
    Route::patch('/currency-preferences', [UserController::class, 'updateCurrencyPreferences']);
});

// Banner相关路由（只读）
Route::get('/banners', [BannerController::class, 'index']);

// 余额相关路由（需要认证）- 两种模式通用
Route::middleware('auth:sanctum')->prefix('balances')->group(function () {
    Route::get('/', [BalanceController::class, 'index']);
    Route::get('/transactions/list', [BalanceController::class, 'transactions']);
    Route::get('/{currency}', [BalanceController::class, 'show']);
});

// 交易记录相关路由（需要认证）- 两种模式通用
Route::middleware('auth:sanctum')->prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);
    Route::get('/types', [TransactionController::class, 'types']);
    Route::get('/{id}', [TransactionController::class, 'show']);
});

// 订单相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
});

// 邀请相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('invitations')->group(function () {
    Route::get('/stats', [InvitationController::class, 'stats']);
    Route::get('/', [InvitationController::class, 'index']);
    Route::get('/{id}/rewards', [InvitationController::class, 'rewardStats']);
});

// KYC相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('kyc')->group(function () {
    Route::get('/', [KycController::class, 'show']);
    Route::post('/', [KycController::class, 'store']);
    Route::post('/advanced', [KycController::class, 'submitAdvanced']);
});

// VIP等级列表（只读）
Route::get('/vip/levels', [VipController::class, 'levels']);

// 签到相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('check-ins')->group(function () {
    Route::post('/', [CheckInController::class, 'store']);
    Route::get('/status', [CheckInController::class, 'status']);
    Route::get('/history', [CheckInController::class, 'history']);
});

// BonusTask 相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('bonus-tasks')->group(function () {
    Route::get('/claimable', [BonusTaskController::class, 'claimable']);
    Route::post('/{id}/claim', [BonusTaskController::class, 'claim']);
});

// 通知相关路由（需要认证）
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/{id}', [NotificationController::class, 'show']);
    Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
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

// 汇率相关路由（只读）
Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);

// 游戏相关路由
Route::prefix('games')->group(function () {
    // 公开路由
    Route::get('/', [GameController::class, 'index']);
    Route::get('/recommend', [GameController::class, 'recommend']);
    Route::post('/{id}/demo', [GameController::class, 'demo']);
    
    // 需要认证的路由
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/recent/list', [GameController::class, 'recent']);
        Route::post('/{id}/session', [GameController::class, 'session']);
    });

    // 动态路由放最后，避免与其他路由冲突
    Route::get('/{id}', [GameController::class, 'show']);
});

// 游戏群组相关路由（只读）
Route::prefix('game-groups')->group(function () {
    Route::get('/', [GameGroupController::class, 'index']);
    Route::get('/category/{category}', [GameGroupController::class, 'getByCategory']);
    Route::get('/{id}', [GameGroupController::class, 'show']);
    Route::get('/{groupId}/games', [GameGroupController::class, 'getGames']);
});

// 帮助中心相关路由（只读）
Route::prefix('help-center')->group(function () {
    // 文章分组相关路由
    Route::prefix('groups')->group(function () {
        Route::get('/', [ArticleGroupController::class, 'index']);
        Route::get('/{id}', [ArticleGroupController::class, 'show']);
    });
    
    // 文章相关路由
    Route::prefix('articles')->group(function () {
        Route::get('/', [ArticleController::class, 'index']);
        Route::get('/{id}', [ArticleController::class, 'show']);
    });
});

// 工具类路由
Route::get('/timestamp', [UtilsController::class, 'timestamp']);
Route::get('/settings', [UtilsController::class, 'settings']);
Route::middleware('auth:sanctum')->post('/upload', [UtilsController::class, 'uploadImage']);

// 游戏提供商回调路由（需要 IP 白名单验证和独立的 rate limit）
Route::prefix('gp')->middleware(['throttle:gp'])->group(function () {
    // Funky 提供商回调
    Route::prefix('funky')->middleware(['provider.ip:funky', 'log.request'])->group(function () {
        Route::post('/Funky/User/GetBalance', [FunkyController::class, 'getBalance']);
        Route::post('/Funky/Bet/CheckBet', [FunkyController::class, 'checkBet']);
        Route::post('/Funky/Bet/PlaceBet', [FunkyController::class, 'bet']);
        Route::post('/Funky/Bet/SettleBet', [FunkyController::class, 'settle']);
        Route::post('/Funky/Bet/CancelBet', [FunkyController::class, 'cancel']);
    });
});

// Sopay 回调路由
Route::post('/sopay/callback', [SopayController::class, 'callback']);

// =============================================================================
// 余额模式相关路由 (根据 BALANCE_MODE 配置切换)
// =============================================================================

// if (config('app.balance_mode') === 'currency') {
    // ========== Currency 模式：传统存款/提款 ==========
    
    // 存款相关路由（需要认证）
    Route::middleware('auth:sanctum')->prefix('deposits')->group(function () {
        Route::get('/', [DepositController::class, 'index']);
        Route::post('/', [DepositController::class, 'store']);
        Route::get('/statuses', [DepositController::class, 'statuses']);
        Route::get('/form-fields', [DepositController::class, 'formFields']);
        Route::post('/extra-step-fields', [DepositController::class, 'extraStepFields']);
        Route::get('/{orderNo}', [DepositController::class, 'show']);
    });

    // 提款相关路由（需要认证）
    Route::middleware('auth:sanctum')->prefix('withdraws')->group(function () {
        Route::get('/', [WithdrawController::class, 'index']);
        Route::post('/', [WithdrawController::class, 'store']);
        Route::get('/statuses', [WithdrawController::class, 'statuses']);
        Route::get('/form-fields', [WithdrawController::class, 'formFields']);
        Route::get('/{orderNo}', [WithdrawController::class, 'show']);
    });
// }

// if (config('app.balance_mode') === 'bundle') {
    // ========== Bundle 模式：GC/SC 双币种捆绑包 ==========
    
    // Bundle 购买相关路由
    Route::prefix('bundles')->group(function () {
        Route::get('/', [BundleController::class, 'index']);
        Route::get('/{id}', [BundleController::class, 'show']);
        
        // 需要认证的路由
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/form-fields', [BundleController::class, 'formFields']);
            Route::get('/purchases/statuses', [BundleController::class, 'purchaseStatuses']);
            Route::post('/purchase', [BundleController::class, 'purchase']);
            Route::get('/purchases/list', [BundleController::class, 'purchases']);
            Route::get('/purchases/{orderNo}', [BundleController::class, 'purchaseDetail']);
        });
    });

    // Redeem 兑换相关路由 (SC -> USD)
    Route::middleware('auth:sanctum')->prefix('redeems')->group(function () {
        Route::get('/', [RedeemController::class, 'index']);
        Route::post('/', [RedeemController::class, 'store']);
        Route::get('/statuses', [RedeemController::class, 'statuses']);
        Route::get('/form-fields', [RedeemController::class, 'formFields']);
        Route::get('/exchange-rate', [RedeemController::class, 'exchangeRate']);
        Route::get('/{orderNo}', [RedeemController::class, 'show']);
    });
// }
