<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    /**
     * 是否对发送验证码等接口执行 reCAPTCHA 校验（需同时配置 secret）
     */
    public function isVerificationEnabled(): bool
    {
        if (! config('services.recaptcha.enabled')) {
            return false;
        }

        return filled(config('services.recaptcha.secret'));
    }

    /**
     * 调用 Google siteverify；v3 时校验 score；可配置 expected_action 与前端 action 一致时再通过
     *
     * @throws Exception
     */
    public function assertVerified(string $token, ?string $remoteIp = null): void
    {
        $secret = config('services.recaptcha.secret');
        if ($secret === null || $secret === '') {
            return;
        }

        if (trim($token) === '') {
            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        try {
            $response = Http::timeout(10)
                ->asForm()
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Recaptcha siteverify request failed', ['exception' => $e->getMessage()]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        if (! $response->successful()) {
            Log::warning('Recaptcha siteverify HTTP error', ['status' => $response->status()]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['success'])) {
            Log::info('Recaptcha verification rejected', ['errors' => $data['error-codes'] ?? []]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        if (isset($data['score'])) {
            $minScore = (float) config('services.recaptcha.min_score', 0.5);
            if ((float) $data['score'] < $minScore) {
                Log::info('Recaptcha score too low', ['score' => $data['score'], 'min' => $minScore]);

                throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
            }
        }

        $expectedAction = config('services.recaptcha.expected_action');
        if (is_string($expectedAction) && $expectedAction !== '') {
            if (($data['action'] ?? '') !== $expectedAction) {
                Log::info('Recaptcha action mismatch', [
                    'expected' => $expectedAction,
                    'actual' => $data['action'] ?? null,
                ]);

                throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
            }
        }
    }
}
