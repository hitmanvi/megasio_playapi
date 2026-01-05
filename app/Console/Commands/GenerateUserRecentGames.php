<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\User;
use App\Models\UserRecentGame;
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
    public function handle(): int
    {
        $userId = $this->argument('user_id');
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
            $deleted = UserRecentGame::where('user_id', $userId)->delete();
            $this->info("已清除 {$deleted} 条现有记录");
        }

        // 生成记录
        $created = 0;
        $now = Carbon::now();
        
        foreach ($games as $index => $game) {
            // 模拟不同的游玩时间，越靠前越近
            $playedAt = $now->copy()->subMinutes($index * 30 + rand(1, 29));
            
            UserRecentGame::updateOrCreate(
                ['user_id' => $userId, 'game_id' => $game->id],
                ['last_played_at' => $playedAt]
            );
            $created++;
        }

        $this->info("成功为用户 {$user->username} (ID: {$userId}) 生成 {$created} 条最近游玩记录");
        
        // 显示生成的记录
        $this->table(
            ['游戏ID', '游戏名称', '最后游玩时间'],
            UserRecentGame::where('user_id', $userId)
                ->orderByDesc('last_played_at')
                ->with('game')
                ->get()
                ->map(fn($record) => [
                    $record->game_id,
                    $record->game?->name ?? 'N/A',
                    $record->last_played_at->format('Y-m-d H:i:s'),
                ])
        );

        return Command::SUCCESS;
    }
}
