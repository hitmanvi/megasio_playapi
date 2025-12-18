<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateGameThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:update-thumbnails {url : JSON数据的URL地址}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从URL获取JSON数据并更新游戏的缩略图，JSON格式: {out_id: thumbnail_url}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = $this->argument('url');

        $this->info("正在从 {$url} 获取数据...");

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                $this->error("请求失败，HTTP状态码: {$response->status()}");
                return 1;
            }

            $data = $response->json();

            if (!is_array($data)) {
                $this->error("返回的数据不是有效的JSON对象");
                return 1;
            }

            $this->info("获取到 " . count($data) . " 条数据");

            $updated = 0;
            $notFound = 0;
            $failed = 0;

            $bar = $this->output->createProgressBar(count($data));
            $bar->start();

            foreach ($data as $outId => $thumbnail) {
                $game = Game::where('out_id', $outId)->first();

                if (!$game) {
                    $notFound++;
                    $bar->advance();
                    continue;
                }

                try {
                    $game->thumbnail = $thumbnail;
                    $game->save();
                    $updated++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("更新游戏 {$outId} 失败: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("更新完成:");
            $this->line("  - 成功更新: {$updated}");
            $this->line("  - 未找到游戏: {$notFound}");
            $this->line("  - 更新失败: {$failed}");

            return 0;
        } catch (\Exception $e) {
            $this->error("发生错误: {$e->getMessage()}");
            return 1;
        }
    }
}

