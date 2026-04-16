<?php

namespace App\Console\Commands\Init;

use App\Models\GameGroup;
use Illuminate\Console\Command;

class InitGameGroups extends Command
{
    protected $signature = 'init:game-groups';

    protected $description = '初始化默认游戏分组：Recommended、Support Bonus（已存在则跳过）';

    /**
     * @return array<int, array{name: string, category: string, sort_id: int}>
     */
    protected function definitions(): array
    {
        return [
            [
                'name' => GameGroup::NAME_RECOMMENDED,
                'category' => GameGroup::CATEGORY_SYSTEM,
                'sort_id' => 1,
            ],
            [
                'name' => GameGroup::NAME_SUPPORT_BONUS,
                'category' => GameGroup::CATEGORY_SYSTEM,
                'sort_id' => 2,
            ],
        ];
    }

    public function handle(): int
    {
        $this->info('初始化 game_groups 默认分组...');

        $created = 0;
        foreach ($this->definitions() as $row) {
            $group = GameGroup::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'category' => $row['category'],
                    'sort_id' => $row['sort_id'],
                    'app_limit' => null,
                    'web_limit' => null,
                    'enabled' => true,
                    'visible' => true,
                ]
            );

            if ($group->wasRecentlyCreated) {
                $this->info("  已创建: {$row['name']}");
                $created++;
            } else {
                $this->line("  已存在/已同步: {$row['name']} (id={$group->id})");
            }
        }

        $this->newLine();
        $this->info('✓ 完成（新建 '.$created.' 条，共 '.count($this->definitions()).' 个默认分组）');

        $rows = GameGroup::query()
            ->whereIn('name', [
                GameGroup::NAME_RECOMMENDED,
                GameGroup::NAME_SUPPORT_BONUS,
            ])
            ->orderBy('sort_id')
            ->get(['id', 'name', 'category', 'sort_id', 'enabled', 'visible']);

        if ($rows->isNotEmpty()) {
            $this->table(
                ['ID', 'name', 'category', 'sort_id', 'enabled', 'visible'],
                $rows->map(fn ($g) => [
                    $g->id,
                    $g->name,
                    $g->category,
                    $g->sort_id,
                    $g->enabled ? 'true' : 'false',
                    $g->visible ? 'true' : 'false',
                ])->all()
            );
        }

        return Command::SUCCESS;
    }
}
