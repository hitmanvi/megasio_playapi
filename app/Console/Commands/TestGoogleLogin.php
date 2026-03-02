<?php

namespace App\Console\Commands;

use App\Services\AuthService;
use Illuminate\Console\Command;

class TestGoogleLogin extends Command
{
    protected $signature = 'test:google-login
                            {id_token : Google ID Token}
                            {--client= : 客户端标识：ios|android|web，默认 web}';

    protected $description = '测试 Google 登录，验证 id_token 并执行登录/注册';

    public function handle(): int
    {
        $idToken = $this->argument('id_token');
        $client = $this->option('client');

        $this->info('测试 Google 登录');
        $this->line("client: " . ($client ?: 'web (默认)'));
        $this->line("id_token: " . substr($idToken, 0, 50) . '...');
        $this->newLine();

        try {
            $authService = new AuthService();
            $result = $authService->loginWithGoogle(
                $idToken,
                null,
                '127.0.0.1',
                'TestGoogleLogin/1.0',
                [],
                $client
            );

            $this->info('✓ 登录成功');
            $this->newLine();
            $this->line('用户: ' . ($result['user']->name ?? '-'));
            $this->line('UID: ' . ($result['user']->uid ?? '-'));
            $this->line('Email: ' . ($result['user']->email ?? '-'));
            $this->line('Token: ' . substr($result['token'], 0, 30) . '...');
            $this->line('Token Type: ' . $result['token_type']);

            return 0;
        } catch (\App\Exceptions\Exception $e) {
            $this->error('登录失败: ' . $e->getMessage());
            $this->line('ErrorCode: ' . $e->getErrorCode());
            return 1;
        } catch (\Exception $e) {
            $this->error('异常: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
