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
use App\Services\ProviderTransactionService;

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
    protected $providerTransactionService;
    public function __construct(string $currency)
    {
        $this->tokenService = new GameProviderTokenService();
        $this->balanceService = new BalanceService();
        $this->providerTransactionService = new ProviderTransactionService();
        $this->loadCurrencyConfig($currency);
    }

    /**
     * 根据 currency 加载配置
     *
     * @param string $currency
     * @return void
     */
    protected function loadCurrencyConfig(string $currency): void
    {
        // 直接以 currency 为 key 加载配置
        $currencyConfig = config("providers.funky.{$currency}", []);

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
            'language' => $this->lang,
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
            'playerId'    => $user->user_id,
            'playerIp'    => request()->ip(),
            'redirectUrl' => config('providers.return_url'),
            'sessionId'   => $token,
            'userName'    => $user->user_id,
        ];

        $resp = $this->postRequest($path, $data);
        $url = $resp['data']['gameUrl'] . '?token=' . $resp['data']['token'];

        return $url;
    }

    public function getBalance(int $userId, string $currency): float
    {
        $balance = $this->balanceService->getBalance($userId, $currency);
        return floatval($balance['available']);
    }

    public function bet($userId, $gameId, $data, $currency=null)
    {
        // $action = $this->providerTransactionService->findByProviderAndTxid(GameProviderEnum::FUNKY->value, $data['refNo']);
        // if ($action) {
        //     throw new Exception(ErrorCode::BET_DUP);
        // }
        // $action = $this->providerTransactionService->create(GameProviderEnum::FUNKY->value, $gameId, $userId, $data['refNo'], $data['roundId'], $data, $data);

        // $this->balanceService->bet($userId, $data['stake'], $gameId, $data['refNo']);
        // $order = $this->orderService->bet($gameId, $userId, Game::PROVIDER_FUNKY, $data['stake'], $data['refNo']);

        // $action->update(['order_id' => $order->id]);

        // return $action;
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

        return $resp->json();
    }
}