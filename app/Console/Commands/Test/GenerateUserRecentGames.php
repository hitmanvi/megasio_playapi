<?php

namespace App\Console\Commands\Test;

use App\Models\Game;
use App\Models\User;
use App\Services\UserRecentGameService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateUserRecentGames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:generate-recent-games 
                            {user_id : 用户ID}
                            {--count=10 : 生成的游戏数量}
                            {--clear : 清除该用户现有的记录后再生成}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为指定用户生成最近游玩游戏记录（测试用）';

    /**
     * Execute the console command.
     */
    public function handle(UserRecentGameService $service): int
    {
        $userId = (int) $this->argument('user_id');
        $count = (int) $this->option('count');
        $clear = $this->option('clear');

        // 验证用户存在
        $user = User::find($userId);
        if (!$user) {
            $this->error("用户 ID {$userId} 不存在");
            return Command::FAILURE;
        }

        // 获取可用游戏
        $games = Game::enabled()->inRandomOrder()->limit($count)->get();
        
        if ($games->isEmpty()) {
            $this->error('数据库中没有可用的游戏');
            return Command::FAILURE;
        }

        // 清除现有记录
        if ($clear) {
            $service->clearCache($userId);
            $this->info("已清除 Redis 缓存");
        }

        // 生成记录
        $now = Carbon::now();
        $records = [];
        
        foreach ($games as $index => $game) {
            // 模拟不同的游玩时间，越靠前越近
            $playedAt = $now->copy()->subMinutes($index * 30 + rand(1, 29));
            
            // 随机生成游玩次数和最大倍数
            $records[] = [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'play_count' => rand(1, 100),
                'max_multiplier' => rand(0, 500) / 10, // 0 - 50x
                'last_played_at' => $playedAt,
            ];
        }

        // 批量写入 Redis
        $service->batchSet($userId, $records);

        $this->info("成功为用户 {$user->username} (ID: {$userId}) 生成 " . count($records) . " 条最近游玩记录");
        
        // 显示生成的记录
        $this->table(
            ['游戏ID', '游戏名称', '游玩次数', '最大倍数', '最后游玩时间'],
            collect($records)->map(fn($record) => [
                $record['game_id'],
                $record['game_name'],
                $record['play_count'],
                number_format($record['max_multiplier'], 2) . 'x',
                $record['last_played_at']->format('Y-m-d H:i:s'),
            ])
        );

        return Command::SUCCESS;
    }
}
