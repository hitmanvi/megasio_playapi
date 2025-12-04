<?php

namespace App\GameProviders;

use App\Contracts\GameProviderInterface;
use App\Enums\GameProvider as GameProviderEnum;
use App\Models\User;
use App\Services\GameProviderTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Exceptions\Exception;
use App\Enums\ErrorCode;
use App\Services\BalanceService;
use App\Services\ProviderCallbackService;
use App\Models\Game;
use Illuminate\Support\Facades\Log;
class FunkyProvider implements GameProviderInterface
{
    protected $apiUrl;
    protected $clientId;
    protected $clientSecret;
    protected $funkyId;
    protected $funkySecret;
    public $lang;
    public $currency;

    protected $tokenService;
    protected $balanceService;
    protected $providerCallbackService;

    const STATUS_RUNNING = 'R';
    const STATUS_WON = 'W';
    const STATUS_LOSS = 'L';
    const STATUS_CANCELED = 'C';
    const STATUS_DRAW = 'D';

    // Funky 错误码常量
    const ERR_INVALID_INPUT = 400;
    const ERR_AUTH = 401;
    const ERR_BALANCE = 402;
    const ERR_BET_DUP = 403;
    const ERR_BET_404 = 404;
    const ERR_SUSPENDED = 405;
    const ERR_OVER_MAX_WIN = 406;
    const ERR_OVER_MAX_LOSE = 407;
    const ERR_SETTLED = 409;
    const ERR_CANCELLED = 410;
    const ERR_GAME_403 = 10005;
    const ERR_SERVER_ERROR = 9999;

    /**
     * 错误码映射（静态）
     */
    protected static array $codeMap = [
        400   => 'Invalid Input',
        401   => 'Player Not Login',
        402   => 'Insufficient Balance',
        403   => 'Bet already exists',
        404   => 'Bet Was Not Found',
        405   => 'Api Suspended',
        406   => 'Over Max Winning',
        407   => 'Over Max Lose',
        409   => 'Bet Already Settled',
        410   => 'Bet Already Cancelled',
        601   => 'Voucher Already Exists',
        602   => 'Voucher Is Not Valid',
        3002  => 'Report Invalid Input',
        3003  => 'Report Page Not Found',
        3004  => 'Report GameCode Not Found',
        10005 => 'GameCode is not allowed',
        9999  => 'Internal Server Error',
    ];

    public function __construct(string $currency='default')
    {
        $this->tokenService = new GameProviderTokenService();
        $this->balanceService = new BalanceService();
        $this->providerCallbackService = new ProviderCallbackService();
        $this->loadCurrencyConfig($currency);
    }

    /**
     * 根据 currency 加载配置
     * 优先使用币种特定配置，如果没有则使用通用配置
     *
     * @param string $currency
     * @return void
     */
    protected function loadCurrencyConfig(string $currency): void
    {
        // 先尝试加载币种特定配置
        $currencyConfig = config("providers.funky.{$currency}", []);
        
        // 如果没有币种特定配置，使用通用配置（default 或直接在 funky 下的配置）
        if (empty($currencyConfig)) {
            $defaultConfig = config("providers.funky.default", []);
            // 如果 default 也没有，尝试直接使用 funky 下的配置（排除 ip_whitelist）
            if (empty($defaultConfig)) {
                $allConfig = config("providers.funky", []);
                unset($allConfig['ip_whitelist']); // 排除 ip_whitelist
                $currencyConfig = $allConfig;
            } else {
                $currencyConfig = $defaultConfig;
            }
        }

        // 加载所有配置
        $this->apiUrl = $currencyConfig['api_url'] ?? null;
        $this->clientId = $currencyConfig['client_id'] ?? null;
        $this->clientSecret = $currencyConfig['client_secret'] ?? null;
        $this->lang = $currencyConfig['lang'] ?? null;
        $this->funkyId = $currencyConfig['funky_id'] ?? null;
        $this->funkySecret = $currencyConfig['funky_secret'] ?? null;
        $this->currency = $currency;
    }

    public function getGameList()
    {
        $path = '/Funky/Game/GetGameList';
        $data = [
            'gameType' => 0,
        ];

        $resp = $this->postRequest($path, $data);

        return $resp['gameList'];
    }

    public function demo(string $gameId, array $params = []): ?string
    {
        return null;
    }

    public function session(string $userId, string $gameId, array $params = []): string
    {

        $path = '/Funky/Game/LaunchGame';
        $user = User::find($userId);
        $token = $this->tokenService->issue(GameProviderEnum::FUNKY->value, $userId, $this->currency);
        if (!$token) {
            throw new Exception(ErrorCode::TOKEN_INVALID);
        }
        $data = [
            'currency'    => $this->currency,
            'gameCode'    => $gameId,
            'language'    => $this->lang,
            'playerId'    => $user->uid,
            'playerIp'    => request()->ip(),
            'redirectUrl' => config('providers.return_url'),
            'sessionId'   => $token,
            'userName'    => $user->uid,
        ];

        $resp = $this->postRequest($path, $data);
        $url = $resp['data']['gameUrl'] . '?token=' . $resp['data']['token'];

        return $url;
    }

    public function postRequest($path, $data)
    {
        $url = $this->apiUrl . $path;
        $headers = [
            'User-Agent'     => $this->clientId,
            'Authentication' => $this->clientSecret,
            'X-Request-ID'   => Str::random(),
        ];

        $resp = Http::withHeaders($headers)->post($url, $data);
        Log::error('FunkyProvider postRequest', [
            'url' => $url,
            'headers' => $headers,
            'data' => $data,
            'response_status' => $resp->status(),
            'response_body' => $resp->body(),
        ]);
        return $resp->json();
    }

    public function checkFunkyHeader($funkyId, $funkySecret)
    {
        return $funkyId == $this->funkyId && $funkySecret == $this->funkySecret;
    }

    /**
     * 返回 Funky 格式的错误响应（静态方法）
     *
     * @param int $code 错误码
     * @return \Illuminate\Http\JsonResponse
     */
    public static function errorResp(int $code): \Illuminate\Http\JsonResponse
    {
        $message = self::$codeMap[$code] ?? 'Unknown error';
        
        return response()->json([
            'data' => null,
            'errorCode' => $code,
            'errorMessage' => $message,
        ]);
    }

    /**
     * 根据订单信息获取 Funky 状态（静态方法）
     *
     * @param float $amount 下注金额
     * @param float $payout 赔付金额
     * @param string|null $orderStatus 订单状态（可选）
     * @return string Funky 状态码
     */
    public static function getStatus(float $amount, float $payout, ?string $orderStatus = null): string
    {
        // 如果订单已取消
        if ($orderStatus === 'CANCELLED') {
            return self::STATUS_CANCELED;
        }

        // 如果订单还在进行中（未完成）
        if ($orderStatus === 'PENDING') {
            return self::STATUS_RUNNING;
        }

        // 根据金额判断状态
        if ($payout > $amount) {
            // 赔付金额大于下注金额，说明赢了
            return self::STATUS_WON;
        } elseif ($payout < $amount) {
            // 赔付金额小于下注金额，说明输了
            return self::STATUS_LOSS;
        } else {
            // 赔付金额等于下注金额，平局
            return self::STATUS_DRAW;
        }
    }
}