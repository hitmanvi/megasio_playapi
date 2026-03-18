<?php

namespace App\Console\Commands\Init;

use Illuminate\Console\Command;

class InitAll extends Command
{
    protected $signature = 'init:all';

    protected $description = '执行所有初始化命令（init:setting、init:agent 等）';

    protected array $initCommands = [
        'init:setting',
        'init:agent',
        'init:opensearch',
    ];

    public function handle(): int
    {
        $this->info('开始执行全部初始化...');
        $this->newLine();

        foreach ($this->initCommands as $command) {
            $this->info(">>> 执行 {$command}");
            $this->call($command);
            $this->newLine();
        }

        $this->info('✓ 全部初始化完成');

        return Command::SUCCESS;
    }
}
