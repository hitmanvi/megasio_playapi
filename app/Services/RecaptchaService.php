<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use Google\ApiCore\ApiException;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class RecaptchaService
{
    private ?RecaptchaEnterpriseServiceClient $enterpriseClient = null;

    /**
     * 是否执行 reCAPTCHA Enterprise 校验（需启用且配置 project_id + site_key）
     */
    public function isVerificationEnabled(): bool
    {
        if (! config('services.recaptcha.enabled')) {
            return false;
        }

        return filled(config('services.recaptcha.enterprise.project_id'))
            && filled(config('services.recaptcha.enterprise.site_key'));
    }

    /**
     * 调用 {@link https://docs.cloud.google.com/php/docs/reference/cloud-recaptcha-enterprise/latest CreateAssessment} 校验 token
     *
     * @throws Exception
     */
    public function assertVerified(string $token, ?string $remoteIp = null): void
    {
        $projectId = config('services.recaptcha.enterprise.project_id');
        $siteKey = config('services.recaptcha.enterprise.site_key');

        if ($projectId === null || $projectId === '' || $siteKey === null || $siteKey === '') {
            return;
        }

        if (trim($token) === '') {
            Log::debug('Recaptcha Enterprise assertVerified rejected: empty token', [
                'remote_ip' => $remoteIp,
            ]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        Log::debug('Recaptcha Enterprise assertVerified start', [
            'remote_ip' => $remoteIp,
            'token_length' => strlen($token),
            'project_id' => $projectId,
        ]);

        $event = (new Event)
            ->setToken($token)
            ->setSiteKey($siteKey);

        if (filled($remoteIp)) {
            $event->setUserIpAddress($remoteIp);
        }

        $expectedAction = config('services.recaptcha.expected_action');
        if (is_string($expectedAction) && $expectedAction !== '') {
            $event->setExpectedAction($expectedAction);
        }

        $assessment = (new Assessment)->setEvent($event);

        $request = (new CreateAssessmentRequest)
            ->setParent(RecaptchaEnterpriseServiceClient::projectName($projectId))
            ->setAssessment($assessment);

        try {
            $response = $this->enterpriseClient()->createAssessment($request);
        } catch (ApiException $e) {
            Log::debug('Recaptcha Enterprise CreateAssessment ApiException', [
                'remote_ip' => $remoteIp,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'metadata' => $e->getMetadata(),
            ]);
            Log::warning('Recaptcha Enterprise API call failed', [
                'message' => $e->getMessage(),
            ]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        Log::debug('Recaptcha Enterprise CreateAssessment response', [
            'remote_ip' => $remoteIp,
            'assessment' => $this->assessmentDebugContext($response),
        ]);

        $tokenProps = $response->getTokenProperties();
        if ($tokenProps === null || ! $tokenProps->getValid()) {
            $reason = $tokenProps?->getInvalidReason();
            Log::info('Recaptcha Enterprise token invalid', [
                'invalid_reason' => $this->invalidReasonLabel($reason),
                'hostname' => $tokenProps?->getHostname(),
                'action' => $tokenProps?->getAction(),
            ]);

            throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
        }

        $minScoreRaw = config('services.recaptcha.min_score');
        if ($minScoreRaw !== null && $minScoreRaw !== '') {
            $minScore = (float) $minScoreRaw;
            $risk = $response->getRiskAnalysis();
            $score = $risk?->getScore();
            if ($score === null || $score < $minScore) {
                Log::debug('Recaptcha Enterprise risk score below threshold', [
                    'remote_ip' => $remoteIp,
                    'score' => $score,
                    'min_score' => $minScore,
                ]);
                Log::info('Recaptcha Enterprise score too low', [
                    'score' => $score,
                    'min' => $minScore,
                ]);

                throw new Exception(ErrorCode::RECAPTCHA_VERIFICATION_FAILED);
            }
        }

        Log::debug('Recaptcha Enterprise assertVerified ok', [
            'remote_ip' => $remoteIp,
            'score' => $response->getRiskAnalysis()?->getScore(),
            'action' => $tokenProps->getAction(),
            'hostname' => $tokenProps->getHostname(),
        ]);
    }

    private function enterpriseClient(): RecaptchaEnterpriseServiceClient
    {
        if ($this->enterpriseClient !== null) {
            return $this->enterpriseClient;
        }

        $options = [
            'transport' => 'rest',
        ];

        $credentials = config('services.recaptcha.enterprise.credentials');
        if (filled($credentials)) {
            $options['credentials'] = $credentials;
        }

        $this->enterpriseClient = new RecaptchaEnterpriseServiceClient($options);

        return $this->enterpriseClient;
    }

    /**
     * @return array<string, mixed>
     */
    private function assessmentDebugContext(Assessment $a): array
    {
        $tp = $a->getTokenProperties();
        $risk = $a->getRiskAnalysis();

        return [
            'name' => $a->getName(),
            'token_valid' => $tp?->getValid(),
            'token_invalid_reason' => $tp ? $this->invalidReasonLabel($tp->getInvalidReason()) : null,
            'token_hostname' => $tp?->getHostname(),
            'token_action' => $tp?->getAction(),
            'risk_score' => $risk?->getScore(),
        ];
    }

    private function invalidReasonLabel(?int $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        try {
            return InvalidReason::name($reason);
        } catch (UnexpectedValueException) {
            return 'UNKNOWN_ENUM_'.$reason;
        }
    }
}
