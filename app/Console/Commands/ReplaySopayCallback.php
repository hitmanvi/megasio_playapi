<?php

namespace App\Console\Commands;

use App\Http\Controllers\SopayController;
use App\Models\SopayCallbackLog;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ReplaySopayCallback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sopay:replay-callback {id : SopayCallbackLog的ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '重放 Sopay 回调请求（根据 SopayCallbackLog ID）';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        
        $log = SopayCallbackLog::find($id);
        
        if (!$log) {
            $this->error("未找到 ID 为 {$id} 的回调日志记录");
            return 1;
        }

        $this->info("找到回调日志记录 #{$id}");
        $this->line("订单ID: {$log->order_id}");
        $this->line("我方订单号: {$log->out_trade_no}");
        $this->line("业务类型: {$log->subject}");
        $this->line("状态: {$log->status}");
        $this->line("金额: {$log->amount}");
        $this->line("签名验证: " . ($log->signature_valid ? '✓ 通过' : '✗ 失败'));
        $this->line("处理结果: {$log->process_result}");
        if ($log->process_error) {
            $this->warn("处理错误: {$log->process_error}");
        }
        $this->newLine();

        // 检查必要的数据
        if (!$log->sign_data) {
            $this->error('日志记录中缺少 sign_data，无法重放');
            return 1;
        }

        if (!$log->signature) {
            $this->error('日志记录中缺少 signature，无法重放');
            return 1;
        }

        // 构建请求
        $requestBody = $log->request_body ?? [];
        $requestHeaders = $log->request_headers ?? [];
        
        // 确保 sign_data 在请求体中
        if (!isset($requestBody['sign_data'])) {
            $requestBody['sign_data'] = $log->sign_data;
        }

        // 构建 Request 对象
        $request = Request::create(
            '/api/sopay/callback',
            'POST',
            $requestBody
        );

        // 设置请求头
        foreach ($requestHeaders as $key => $value) {
            if (is_array($value) && !empty($value)) {
                // Laravel 的 headers 可能是数组，取第一个值
                $headerValue = is_array($value[0]) ? $value[0][0] ?? '' : $value[0];
                $request->headers->set($key, $headerValue);
            } elseif (is_string($value)) {
                $request->headers->set($key, $value);
            }
        }

        // 确保 signature header 存在（优先级最高）
        $request->headers->set('signature', $log->signature);

        $this->info('开始重放回调请求...');
        $this->line('Sign Data: ' . substr($log->sign_data, 0, 100) . '...');
        $this->line('Signature: ' . substr($log->signature, 0, 50) . '...');
        $this->newLine();

        try {
            // 创建 controller 实例并调用 callback 方法
            $controller = app(SopayController::class);
            $response = $controller->callback($request);
            
            $this->info('回调处理完成');
            $this->line('响应内容: ' . ($response ?: '(空)'));
            
            if ($response === 'ok') {
                $this->info('✓ 回调处理成功');
            } else {
                $this->warn('⚠ 回调返回非 ok 响应');
            }

            // 检查最新的日志记录
            $latestLog = SopayCallbackLog::where('order_id', $log->order_id)
                ->where('out_trade_no', $log->out_trade_no)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestLog && $latestLog->id != $log->id) {
                $this->newLine();
                $this->info('最新日志记录 #' . $latestLog->id);
                $this->line('签名验证: ' . ($latestLog->signature_valid ? '✓ 通过' : '✗ 失败'));
                $this->line('处理结果: ' . ($latestLog->process_result ?: '(空)'));
                if ($latestLog->process_error) {
                    $this->warn('处理错误: ' . $latestLog->process_error);
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('重放失败: ' . $e->getMessage());
            $this->error('堆栈跟踪:');
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
