<?php

namespace App\Console\Commands\Test;

use App\Models\Game;
use App\Models\Brand;
use App\Models\GameCategory;
use App\Models\Theme;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class GenerateGames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:generate-test {count=10 : 要生成的游戏数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '随机生成指定数量的测试游戏数据';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->argument('count');

        if ($count <= 0) {
            $this->error('数量必须大于 0');
            return Command::FAILURE;
        }

        // 获取现有的数据
        $brands = Brand::enabled()->get();
        $categories = GameCategory::enabled()->get();
        $themes = Theme::enabled()->get();

        if ($brands->isEmpty()) {
            $this->error('没有可用的品牌，请先创建品牌数据');
            return Command::FAILURE;
        }

        if ($categories->isEmpty()) {
            $this->error('没有可用的分类，请先创建分类数据');
            return Command::FAILURE;
        }

        $faker = Faker::create();

        $this->info("开始生成 {$count} 个测试游戏...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        DB::beginTransaction();
        try {
            $usedOutIds = [];
            for ($i = 0; $i < $count; $i++) {
                // 随机选择品牌和分类
                $brand = $brands->random();
                $category = $categories->random();

                // 生成唯一的 out_id
                $baseTime = time();
                $counter = 0;
                do {
                    $outId = strtolower($brand->provider) . '_' . str_pad((string) ($baseTime + $i * 1000 + $counter), 12, '0', STR_PAD_LEFT);
                    $counter++;
                } while (in_array($outId, $usedOutIds) && $counter < 10000);
                
                $usedOutIds[] = $outId;

                // 生成游戏名称
                $gameName = $faker->words(rand(2, 4), true);
                $gameName = ucwords($gameName);

                // 获取当前最大的 sort_id
                $maxSortId = Game::max('sort_id') ?? 0;

                // 创建游戏
                $game = Game::create([
                    'brand_id' => $brand->id,
                    'category_id' => $category->id,
                    'out_id' => $outId,
                    'name' => $gameName,
                    'thumbnail' => $faker->imageUrl(640, 480, 'games', true, $gameName),
                    'sort_id' => $maxSortId + $i + 1,
                    'enabled' => $faker->boolean(90), // 90% 概率启用
                    'memo' => $faker->optional(0.7)->sentence(), // 70% 概率有备注
                    'languages' => $faker->randomElements(
                        ['en', 'zh-CN', 'ja', 'ko', 'es', 'fr'],
                        rand(2, 4)
                    ),
                ]);

                // 随机关联主题（1-3个）
                if ($themes->isNotEmpty()) {
                    $selectedThemes = $themes->random(rand(1, min(3, $themes->count())));
                    $game->themes()->attach($selectedThemes->pluck('id')->toArray());
                }

                // 创建多语言翻译
                $locales = ['en', 'zh-CN', 'ja', 'ko'];
                foreach ($locales as $locale) {
                    Translation::create([
                        'translatable_type' => Game::class,
                        'translatable_id' => $game->id,
                        'field' => 'name',
                        'locale' => $locale,
                        'value' => $this->generateTranslatedName($gameName, $locale, $faker),
                    ]);
                }

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();
            $this->info("成功生成 {$count} 个测试游戏！");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error('生成失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 生成翻译名称
     */
    private function generateTranslatedName(string $englishName, string $locale, $faker): string
    {
        if ($locale === 'en') {
            return $englishName;
        }

        // 为不同语言生成随机翻译名称
        $translatedNames = [
            'zh-CN' => $faker->words(rand(2, 4), true) . '游戏',
            'ja' => $faker->words(rand(2, 3), true) . 'ゲーム',
            'ko' => $faker->words(rand(2, 3), true) . '게임',
        ];

        return $translatedNames[$locale] ?? $englishName;
    }
}
