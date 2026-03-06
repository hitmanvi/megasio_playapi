<?php

namespace App\Console\Commands;

use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use Illuminate\Console\Command;

class TestEventServices extends Command
{
    protected $signature = 'test:event-services
                            {--event=register : 事件名称，支持 register|begin_checkout|purchase|first_purchase (Kochava: Registration Complete|Checkout Start|Purchase|First Deposit) 或自定义}
                            {--service=both : 目标服务：kochava|facebook|both}
                            {--user-id=1 : 用户 ID}
                            {--uid=TEST001 : 用户 UID}
                            {--email= : 邮箱（Facebook 推荐）}
                            {--order-no=TEST_ORD_001 : 订单号}
                            {--amount=10.00 : 金额}
                            {--currency=USD : 币种}
                            {--event-id= : 自定义 event_id，不传则按规则生成}';

    protected $description = '测试 Kochava 和 Facebook Conversions 事件发送，支持自定义事件名';

    public function handle(): int
    {
        $event = $this->option('event');
        $service = $this->option('service');
        $userId = (int) $this->option('user-id');
        $uid = $this->option('uid');
        $email = $this->option('email');
        $orderNo = $this->option('order-no');
        $amount = (float) $this->option('amount');
        $currency = strtoupper($this->option('currency'));
        $customEventId = $this->option('event-id');

        $validServices = ['kochava', 'facebook', 'both'];
        if (!in_array($service, $validServices)) {
            $this->error("service 必须是: " . implode('|', $validServices));
            return 1;
        }

        $deviceInfo = [
            'kochava_device_id' => 'TEST_DEVICE_001',
            'device_ids' => ['idfa' => '00000000-0000-0000-0000-000000000001'],
            'device_ua' => 'TestEventServices/1.0',
            'origination_ip' => '127.0.0.1',
            'usertime' => time(),
        ];

        $sendKochava = in_array($service, ['kochava', 'both']);
        $sendFacebook = in_array($service, ['facebook', 'both']);

        $this->info("测试事件: {$event} | 目标: {$service}");
        $this->line('参数: uid=' . $uid . ', order_no=' . $orderNo . ', amount=' . $amount . ', currency=' . $currency);
        $this->newLine();

        $kochavaOk = false;
        $facebookOk = false;

        if ($sendKochava) {
            $this->line('--- Kochava ---');
            $this->line('Event: ' . $event . ' → ' . $this->getKochavaEventName($event));
            $kochavaService = new KochavaService();
            if (!$kochavaService->isEnabled()) {
                $this->warn('Kochava 未启用（KOCHAVA_ENABLED 或 KOCHAVA_APP_ID 未配置）');
            } else {
                $eventData = $this->buildKochavaEventData($event, $userId, $uid, $orderNo, $amount, $currency, $customEventId);
                $kochavaOk = $kochavaService->sendEvent($event, $eventData, $deviceInfo);
                $this->line($kochavaOk ? '✓ Kochava 发送成功' : '✗ Kochava 发送失败');
            }
        }

        if ($sendFacebook) {
            $this->line('--- Facebook Conversions ---');
            $facebookService = new FacebookConversionsService();
            if (!$facebookService->isEnabled()) {
                $this->warn('Facebook Conversions 未启用（FACEBOOK_CONVERSIONS_ENABLED 或 pixel_id/access_token 未配置）');
            } else {
                [$userData, $customData, $eventId] = $this->buildFacebookEventData($event, $userId, $uid, $email, $orderNo, $amount, $currency, $customEventId);
                $facebookEventName = $this->getFacebookEventName($event);
                $facebookOk = $facebookService->sendEvent($facebookEventName, $userData, $customData, $eventId);
                $this->line($facebookOk ? '✓ Facebook 发送成功' : '✗ Facebook 发送失败');
            }
        }

        $this->newLine();
        if ($kochavaOk || $facebookOk) {
            $this->info('测试完成');
            return 0;
        }

        $this->error('所有服务均未成功发送');
        return 1;
    }

    protected function getFacebookEventName(string $event): string
    {
        return match ($event) {
            'register' => 'CompleteRegistration',
            'begin_checkout' => 'InitiateCheckout',
            'purchase' => 'Purchase',
            'first_purchase' => 'FirstDeposit',
            default => $event,
        };
    }

    /**
     * Kochava event name mapping (per Post-Install Event Examples)
     *
     * @see https://support.kochava.com/articles/reference-information/2213-post-install-event-examples
     */
    protected function getKochavaEventName(string $event): string
    {
        return match ($event) {
            'register' => 'Registration Complete',
            'begin_checkout' => 'Checkout Start',
            'purchase' => 'Purchase',
            'first_purchase' => 'First Deposit',
            default => $event,
        };
    }

    protected function buildKochavaEventData(string $event, int $userId, string $uid, string $orderNo, float $amount, string $currency, ?string $customEventId = null): array
    {
        $base = [
            'uid' => $uid,
            'currency' => $currency,
        ];

        $eventId = $customEventId ?? match ($event) {
            'register' => 'register_' . $uid,
            'begin_checkout' => 'begin_checkout_' . $orderNo,
            'purchase', 'first_purchase' => $event . '_' . $orderNo,
            default => $event . '_' . $orderNo,
        };

        $data = array_merge($base, ['event_id' => $eventId]);
        if (in_array($event, ['begin_checkout', 'purchase', 'first_purchase'])) {
            $data['order_no'] = $orderNo;
            $data['amount'] = $amount;
        } elseif ($amount > 0) {
            $data['order_no'] = $orderNo;
            $data['amount'] = $amount;
        }

        return $data;
    }

    protected function buildFacebookEventData(string $event, int $userId, string $uid, ?string $email, string $orderNo, float $amount, string $currency, ?string $customEventId = null): array
    {
        $userData = [
            'client_ip_address' => '127.0.0.1',
            'client_user_agent' => 'TestEventServices/1.0',
            'event_source_url' => config('app.web_url', 'https://example.com'),
            'event_time' => time(),
        ];
        if ($email) {
            $userData['em'] = $email;
        }

        $eventId = $customEventId ?? match ($event) {
            'register' => 'register_' . $uid,
            'begin_checkout' => 'begin_checkout_' . $orderNo,
            'purchase', 'first_purchase' => $event . '_' . $orderNo,
            default => $event . '_' . $orderNo,
        };

        $customData = [];
        if ($event === 'register') {
            $customData = ['status' => 'registered'];
        } elseif (in_array($event, ['begin_checkout', 'purchase', 'first_purchase']) || $amount > 0) {
            $customData = ['currency' => strtolower($currency), 'value' => $amount];
        }

        return [$userData, $customData, $eventId];
    }
}
