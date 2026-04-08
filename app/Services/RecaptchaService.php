<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use Illuminate\Support\Facades\Log;
use ReCaptcha\ReCaptcha;

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
     * 使用 {@see ReCaptcha} 调用 Google siteverify；v3 在成功后按 min_score 校验分数；
     * 配置了 expected_action 时由库内与 token 中的 action 比对
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
            Log::debug('Recaptcha assertVerified rejected: empty token', [
                'remote_ip' => $remoteIp,
            ]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        Log::debug('Recaptcha assertVerified start', [
            'remote_ip' => $remoteIp,
            'token_length' => strlen($token),
        ]);

        $recaptcha = new ReCaptcha($secret);
        $expectedAction = config('services.recaptcha.expected_action');
        if (is_string($expectedAction) && $expectedAction !== '') {
            $recaptcha->setExpectedAction($expectedAction);
        }

        $resp = $recaptcha->verify($token, $remoteIp);

        Log::debug('Recaptcha siteverify response', [
            'remote_ip' => $remoteIp,
            'response' => $resp->toArray(),
        ]);

        if (! $resp->isSuccess()) {
            $errors = $resp->getErrorCodes();
            if (in_array(ReCaptcha::E_CONNECTION_FAILED, $errors, true)) {
                Log::warning('Recaptcha siteverify connection failed', ['errors' => $errors]);
            } elseif (in_array(ReCaptcha::E_INVALID_JSON, $errors, true)) {
                Log::warning('Recaptcha siteverify invalid JSON', ['errors' => $errors]);
            } else {
                Log::info('Recaptcha verification rejected', ['errors' => $errors]);
            }

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        $score = $resp->getScore();
        if ($score !== null) {
            $minScore = (float) config('services.recaptcha.min_score', 0.5);
            if ($score < $minScore) {
                Log::debug('Recaptcha score below threshold', [
                    'remote_ip' => $remoteIp,
                    'score' => $score,
                    'min_score' => $minScore,
                ]);
                Log::info('Recaptcha score too low', ['score' => $score, 'min' => $minScore]);

                throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
            }
        }

        Log::debug('Recaptcha assertVerified ok', [
            'remote_ip' => $remoteIp,
            'score' => $resp->getScore(),
            'action' => $resp->getAction(),
            'hostname' => $resp->getHostname(),
        ]);
    }
}
