<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use App\GameProviders\FunkyProvider;
use App\Enums\GameProvider as GameProviderEnum;
use App\Models\Brand;
use App\Models\GameSyncLog;

class ImportFunkyGame extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:funky_games';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Funky games from provider';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始同步 Funky 游戏列表...');

        $startedAt = now();
        $syncLog = null;

        try {
            // 首次插入/创建时 brand name、provider 都是 'funky'
            // 注意：brand 字段和 provider 字段都赋值为 'funky'
            $brand = Brand::firstOrCreate([
                'name' => 'funky',
                'provider' => GameProviderEnum::FUNKY->value
            ]);

            // 创建同步记录
            $syncLog = GameSyncLog::create([
                'provider' => GameProviderEnum::FUNKY->value,
                'brand_id' => $brand->id,
                'status' => GameSyncLog::STATUS_SUCCESS,
                'started_at' => $startedAt,
            ]);

            // 使用默认货币创建 FunkyProvider（getGameList 不依赖货币）
            $service = new FunkyProvider();
            $games = $service->getGameList();

            $this->info('从 Funky 获取到 ' . count($games) . ' 个游戏');

            $languageCodes = [
                'EN',     // English
                'ZH_TW',  // ZH_TW
                'ZH_CN',  // ZH_CN
                'TH_TH',  // TH_TH
                'VI_VN',  // VI_VN
                'ID_ID',  // ID_ID
                'MY_MM',  // MY_MM
                'HI_IN',  // HI_IN
                'MS_MY',  // MS_MY
                'KO_KR',  // KO_KR
                'JA_JP',  // JA_JP
                'DE_DE',  // DE_DE
                'ES_ES',  // ES_ES
                'FR_FR',  // FR_FR
                'RU_RU',  // RU_RU
                'PT_PT',  // PT_PT
                'ES_LA',  // ES_LA
                'PT_BR',  // PT_BR
                'AR_AE',  // AR_AE
                'BN_BD',  // BN_BD
                'TR_TR',  // TR_TR
                'EN_SC',  // EN_SC
                'MM_SC',  // MM_SC
            ];

            // 统计信息
            $availableCount = 0;
            $maintenanceCount = 0;
            $updatedCount = 0;
            $createdCount = 0;

            // 只导入 gameStatus 为 Ready 的游戏
            $this->info('开始处理游戏...');
            foreach ($games as $d) {
                if ($d['gameStatus'] === 'Ready') {
                    $game = Game::updateOrCreate(
                        ['out_id' => $d['gameCode'], 'brand_id' => $brand->id],
                        [
                            'name' => $d['gameName'],
                            'demo_url' => $d['demoGameUrl'] ?? null,
                            'languages' => $languageCodes,
                            'provider_status' => Game::PROVIDER_STATUS_AVAILABLE,
                            'has_demo' => $d['demoGameUrl'] ? true : false,
                        ]
                    );

                    if ($game->wasRecentlyCreated) {
                        $createdCount++;
                    } else {
                        $updatedCount++;
                    }
                    $availableCount++;
                } else {
                    $updated = Game::where('out_id', $d['gameCode'])
                        ->where('brand_id', $brand->id)
                        ->update([
                            'provider_status' => Game::PROVIDER_STATUS_MAINTENANCE,
                            'enabled' => false
                        ]);
                    if ($updated > 0) {
                        $maintenanceCount++;
                    }
                }
            }

            $outIds = collect($games)->pluck('gameCode')->toArray();
            // 标记不存在的游戏为删除状态
            $query = Game::where('brand_id', $brand->id)
                ->whereNotIn('out_id', $outIds);
            $deletedCount = $query->count();
            $query->update([
                'enabled' => false,
                'provider_status' => Game::PROVIDER_STATUS_DELETED
            ]);

            // 更新同步记录
            if ($syncLog) {
                $syncLog->update([
                    'total_count' => count($games),
                    'available_count' => $availableCount,
                    'maintenance_count' => $maintenanceCount,
                    'deleted_count' => $deletedCount,
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'status' => GameSyncLog::STATUS_SUCCESS,
                    'finished_at' => now(),
                ]);
            }

            // 输出同步结果
            $this->info('');
            $this->info('=== 同步完成 ===');
            $this->info("可用游戏: {$availableCount} 个 (新建: {$createdCount}, 更新: {$updatedCount})");
            $this->info("维护中游戏: {$maintenanceCount} 个");
            $this->info("已删除游戏: {$deletedCount} 个");
            $this->info("总计处理: " . count($games) . " 个游戏");

            return 0;
        } catch (\Exception $e) {
            // 更新同步记录为失败状态
            if ($syncLog) {
                $syncLog->update([
                    'status' => GameSyncLog::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }

            $this->error('同步失败: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }
    }
}
