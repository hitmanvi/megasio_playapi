<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSopayCallback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sopay-callback {--url= : 回调URL，默认使用配置}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试 Sopay 回调接口';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $callbackUrl = $this->option('url') ?: config('services.sopay.callback_url');

        if (!$callbackUrl) {
            $this->error('回调URL未配置，请使用 --url 参数或配置 SOPAY_CALLBACK_URL');
            return 1;
        }

        // 固定的测试数据
        $body = [
            'address' => null,
            'amount' => '10.00000000',
            'coin' => 'USD',
            'coin_type' => 'USD',
            'created_at' => '2025-12-08T12:36:08.000000Z',
            'direction' => 'in',
            'error_message' => null,
            'exception_settle_status' => 0,
            'expired_at' => '2025-12-08T12:51:08.000000Z',
            'extra_info' => null,
            'fee' => '0.00000000',
            'is_success' => false,
            'lock_status' => 0,
            'mine_fee' => '0.00000000',
            'order_id' => '20251208073608120936',
            'out_trade_no' => 'DEP01KBYE78EKGF66ANSVY6J2WPCB',
            'paid_at' => null,
            'pay_amount' => null,
            'payment' => [
                'hash_id' => '1kvqvgq8p4',
                'name' => 'PayPal',
            ],
            'sign_data' => '{"amount":"10.00000000","order_id":"20251208073608120936","out_trade_no":"DEP01KBYE78EKGF66ANSVY6J2WPCB","pay_amount":"","status":0}',
            'status' => 0,
            'subject' => 'deposit',
            'thirdparty_order_id' => null,
            'txid' => null,
            'type' => 1,
            'type_alias' => 'USD',
            'user_address' => null,
            'user_ip' => '158.247.211.126',
        ];

        // 固定的请求头
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'megascash-callback',
            'Signature' => 'kFu7Kx7qXeQuiX+v9NOfu9Edofn+NWxALDqXcrNv62MBrDl9wC0Kj7EdX7g6q2dlQgZunPjItqNDiIElqs9Ws+Fx4pP89EYImWlzEUOtVEruN4CKwa8A7Ah061OWTzAm961GO3vcmDeLYdMQWVDbO9c6c5i0q3/5JEka+1QPKf8HHozZ4T9XeYhKjkUknick8JfEKqAREf+oJNLFEG7z6yPpY+E9KGvp6wna/94Nd16IzdTa0W0JME6fojUC62dJI5T9S3IOqdKemAsh99AUAHvrr6wW69OwnperYy579O9gvGX+SK47cVRIpxF8J+9hG709KludhhAb+6skjQwXLA==',
        ];

        $this->info('发送回调请求...');
        $this->line('URL: ' . $callbackUrl);
        $this->newLine();

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($callbackUrl, $body);

            $this->info('响应状态码: ' . $response->status());
            $this->line('响应内容: ' . $response->body());

            if ($response->body() === 'ok') {
                $this->info('✓ 回调处理成功');
            } else {
                $this->warn('⚠ 回调返回非 ok 响应');
            }
        } catch (\Exception $e) {
            $this->error('请求失败: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
