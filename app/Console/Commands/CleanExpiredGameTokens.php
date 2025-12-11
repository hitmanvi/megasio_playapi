<?php

namespace App\Console\Commands;

use App\Services\GameProviderTokenService;
use Illuminate\Console\Command;

class CleanExpiredGameTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:clean-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理过期的游戏提供商 Token';

    /**
     * Execute the console command.
     */
    public function handle(GameProviderTokenService $service): int
    {
        $count = $service->cleanExpired();
        $this->info("已清理 {$count} 个过期 Token");
        
        return Command::SUCCESS;
    }
}

