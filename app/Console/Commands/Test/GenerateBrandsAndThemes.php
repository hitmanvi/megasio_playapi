<?php

namespace App\Console\Commands\Test;

use App\Models\Brand;
use App\Models\Theme;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Faker\Generator as Faker;

class GenerateBrandsAndThemes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brands-themes:generate-test 
                            {--brands=10 : 要生成的品牌数量}
                            {--themes=10 : 要生成的主题数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '随机生成指定数量的测试品牌和主题数据';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $brandCount = (int) $this->option('brands');
        $themeCount = (int) $this->option('themes');

        if ($brandCount < 0 || $themeCount < 0) {
            $this->error('数量必须大于等于 0');
            return Command::FAILURE;
        }

        $faker = \Faker\Factory::create();

        DB::beginTransaction();
        try {
            // 生成品牌
            if ($brandCount > 0) {
                $this->info("开始生成 {$brandCount} 个测试品牌...");
                $brandBar = $this->output->createProgressBar($brandCount);
                $brandBar->start();

                $maxBrandSortId = Brand::max('sort_id') ?? 0;
                $usedProviders = Brand::pluck('provider')->toArray();

                for ($i = 0; $i < $brandCount; $i++) {
                    $this->generateBrand($faker, $maxBrandSortId + $i + 1, $usedProviders);
                    $brandBar->advance();
                }

                $brandBar->finish();
                $this->newLine();
                $this->info("成功生成 {$brandCount} 个测试品牌！");
            }

            // 生成主题
            if ($themeCount > 0) {
                $this->info("开始生成 {$themeCount} 个测试主题...");
                $themeBar = $this->output->createProgressBar($themeCount);
                $themeBar->start();

                $maxThemeSortId = Theme::max('sort_id') ?? 0;

                for ($i = 0; $i < $themeCount; $i++) {
                    $this->generateTheme($faker, $maxThemeSortId + $i + 1);
                    $themeBar->advance();
                }

                $themeBar->finish();
                $this->newLine();
                $this->info("成功生成 {$themeCount} 个测试主题！");
            }

            DB::commit();

            if ($brandCount > 0 || $themeCount > 0) {
                $this->newLine();
                $this->info('所有数据生成完成！');
            } else {
                $this->info('没有需要生成的数据。');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine();
            $this->error('生成失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 生成品牌
     */
    private function generateBrand(\Faker\Generator $faker, int $sortId, array &$usedProviders): void
    {
        // 生成唯一的 provider
        $provider = $this->generateUniqueProvider($faker, $usedProviders);
        $usedProviders[] = $provider;

        // 生成品牌名称
        $brandName = $faker->words(rand(1, 3), true);
        $brandName = ucwords($brandName);

        // 创建品牌
        $brand = Brand::create([
            'name' => $brandName,
            'provider' => $provider,
            'restricted_region' => $faker->optional(0.3)->randomElements(['CN', 'RU', 'US', 'JP', 'KR'], rand(0, 3)),
            'sort_id' => $sortId,
            'enabled' => $faker->boolean(90), // 90% 概率启用
            'maintain_start' => null,
            'maintain_end' => null,
            'maintain_auto' => false,
        ]);

        // 创建多语言翻译
        $locales = ['en', 'zh-CN', 'ja', 'ko'];
        $translations = [];
        foreach ($locales as $locale) {
            $translations[$locale] = $this->generateTranslatedName($brandName, $locale, $faker, true);
        }
        $brand->setNames($translations);
    }

    /**
     * 生成主题
     */
    private function generateTheme(\Faker\Generator $faker, int $sortId): void
    {
        // 生成主题名称
        $themeName = $faker->words(rand(1, 2), true);
        $themeName = ucwords($themeName);

        // 生成随机文本用于图片 URL
        $randomText = $faker->bothify('?##??');
        $icon = "https://dummyimage.com/100x100/000/fff&text=" . urlencode($randomText);

        // 创建主题
        $theme = Theme::create([
            'name' => $themeName,
            'icon' => $icon,
            'enabled' => $faker->boolean(90), // 90% 概率启用
            'sort_id' => $sortId,
        ]);

        // 创建多语言翻译
        $locales = ['en', 'zh-CN', 'ja', 'ko'];
        $translations = [];
        foreach ($locales as $locale) {
            $translations[$locale] = $this->generateTranslatedName($themeName, $locale, $faker, false);
        }
        $theme->setNames($translations);
    }

    /**
     * 生成唯一的 provider
     */
    private function generateUniqueProvider(\Faker\Generator $faker, array $usedProviders): string
    {
        $counter = 0;
        do {
            $provider = strtolower($faker->word() . '_' . $faker->randomNumber(4));
            $counter++;
        } while (in_array($provider, $usedProviders) && $counter < 1000);

        return $provider;
    }

    /**
     * 生成翻译名称
     */
    private function generateTranslatedName(string $englishName, string $locale, \Faker\Generator $faker, bool $isBrand = true): string
    {
        if ($locale === 'en') {
            return $englishName;
        }

        // 为不同语言生成随机翻译名称
        if ($isBrand) {
            $translatedNames = [
                'zh-CN' => $faker->words(rand(1, 3), true) . '品牌',
                'ja' => $faker->words(rand(1, 2), true) . 'ブランド',
                'ko' => $faker->words(rand(1, 2), true) . '브랜드',
            ];
        } else {
            $translatedNames = [
                'zh-CN' => $faker->words(rand(1, 2), true) . '主题',
                'ja' => $faker->words(rand(1, 2), true) . 'テーマ',
                'ko' => $faker->words(rand(1, 2), true) . '테마',
            ];
        }

        return $translatedNames[$locale] ?? $englishName;
    }
}
