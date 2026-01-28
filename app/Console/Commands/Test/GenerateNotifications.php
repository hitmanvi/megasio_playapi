<?php

namespace App\Console\Commands\Test;

use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generate-notifications 
                            {user_id? : 用户ID（生成system类型时可忽略）} 
                            {--count=10 : 生成的通知数量}
                            {--type=user : 消息类型：system（系统消息）、user（用户消息）}
                            {--category= : 消息分类，不指定则随机生成}
                            {--system-count=5 : 同时生成的系统消息数量（仅在type=user时生效）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为指定用户生成测试通知数据';

    /**
     * 消息分类配置
     */
    protected array $categoryConfigs = [
        Notification::CATEGORY_DEPOSIT_SUCCESS => [
            'title' => '充值成功',
            'content_template' => '您的充值已成功到账，金额：{amount} {currency}',
            'data_template' => ['amount' => 100.00, 'currency' => 'USD', 'order_no' => 'DEP{id}'],
        ],
        Notification::CATEGORY_WITHDRAW_SUCCESS => [
            'title' => '提现成功',
            'content_template' => '您的提现已成功处理，金额：{amount} {currency}',
            'data_template' => ['amount' => 50.00, 'currency' => 'USD', 'order_no' => 'WD{id}'],
        ],
        Notification::CATEGORY_VIP_LEVEL_UP => [
            'title' => 'VIP等级提升',
            'content_template' => '恭喜您！您的VIP等级已提升至 {level}',
            'data_template' => ['level' => 'Gold', 'level_name' => 'Gold'],
        ],
        Notification::CATEGORY_BONUS_TASK_COMPLETED => [
            'title' => '奖励任务完成',
            'content_template' => '恭喜您完成奖励任务：{task_name}，奖励金额：{amount} {currency}',
            'data_template' => ['task_name' => '首充任务', 'amount' => 20.00, 'currency' => 'USD'],
        ],
        Notification::CATEGORY_INVITATION_REWARD => [
            'title' => '邀请奖励',
            'content_template' => '您获得了邀请奖励，金额：{amount} {currency}',
            'data_template' => ['amount' => 10.00, 'currency' => 'USD', 'source_type' => 'bet'],
        ],
        Notification::CATEGORY_SYSTEM_ANNOUNCEMENT => [
            'title' => '系统公告',
            'content_template' => '系统维护通知：{message}',
            'data_template' => ['message' => '系统将于今晚进行维护，预计持续2小时'],
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id') ? (int) $this->argument('user_id') : null;
        $count = (int) $this->option('count');
        $type = $this->option('type');
        $category = $this->option('category');
        $systemCount = (int) $this->option('system-count');

        // 验证类型
        if (!in_array($type, [Notification::TYPE_SYSTEM, Notification::TYPE_USER])) {
            $this->error("类型必须是 system 或 user");
            return Command::FAILURE;
        }

        // 如果是用户消息，验证用户是否存在
        if ($type === Notification::TYPE_USER) {
            if (!$userId) {
                $this->error("生成用户消息时必须提供 user_id");
                return Command::FAILURE;
            }
            $user = User::find($userId);
            if (!$user) {
                $this->error("用户 ID {$userId} 不存在");
                return Command::FAILURE;
            }
        }

        // 验证分类（如果指定）
        if ($category && !isset($this->categoryConfigs[$category])) {
            $this->error("无效的分类：{$category}");
            $this->info("可用的分类：" . implode(', ', array_keys($this->categoryConfigs)));
            return Command::FAILURE;
        }

        $totalCount = $count;
        if ($type === Notification::TYPE_USER && $systemCount > 0) {
            $totalCount += $systemCount;
            $this->info("为用户 {$user->name} (ID: {$userId}) 生成 {$count} 条用户消息和 {$systemCount} 条系统消息...");
        } else {
            if ($type === Notification::TYPE_USER) {
                $this->info("为用户 {$user->name} (ID: {$userId}) 生成 {$count} 条 {$type} 类型的通知...");
            } else {
                $this->info("生成 {$count} 条 {$type} 类型的通知...");
            }
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $categories = $category ? [$category] : array_keys($this->categoryConfigs);
        $generated = 0;
        $systemGenerated = 0;

        // 生成指定类型的通知
        for ($i = 0; $i < $count; $i++) {
            // 随机选择分类（如果未指定）
            $selectedCategory = $category ?: $categories[array_rand($categories)];
            $config = $this->categoryConfigs[$selectedCategory];

            // 生成通知数据
            $notificationData = $this->generateNotificationData($selectedCategory, $config, $generated + 1);

            DB::transaction(function () use ($userId, $type, $selectedCategory, $notificationData) {
                Notification::create([
                    'user_id' => $type === Notification::TYPE_SYSTEM ? null : $userId,
                    'type' => $type,
                    'category' => $selectedCategory,
                    'title' => $notificationData['title'],
                    'content' => $notificationData['content'],
                    'data' => $notificationData['data'],
                    'read_at' => rand(0, 100) < 30 ? Carbon::now()->subHours(rand(1, 24)) : null, // 30% 概率已读
                    'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]);
            });

            $generated++;
            $bar->advance();
        }

        // 如果生成用户消息且指定了系统消息数量，额外生成系统消息
        if ($type === Notification::TYPE_USER && $systemCount > 0) {
            for ($i = 0; $i < $systemCount; $i++) {
                // 随机选择分类
                $selectedCategory = $categories[array_rand($categories)];
                $config = $this->categoryConfigs[$selectedCategory];

                // 生成通知数据
                $notificationData = $this->generateNotificationData($selectedCategory, $config, $generated + $systemGenerated + 1);

                DB::transaction(function () use ($selectedCategory, $notificationData) {
                    Notification::create([
                        'user_id' => null,
                        'type' => Notification::TYPE_SYSTEM,
                        'category' => $selectedCategory,
                        'title' => $notificationData['title'],
                        'content' => $notificationData['content'],
                        'data' => $notificationData['data'],
                        'read_at' => rand(0, 100) < 30 ? Carbon::now()->subHours(rand(1, 24)) : null, // 30% 概率已读
                        'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                    ]);
                });

                $systemGenerated++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        
        if ($type === Notification::TYPE_USER && $systemCount > 0) {
            $this->info("成功生成 {$generated} 条用户消息和 {$systemGenerated} 条系统消息！");
        } else {
            $this->info("成功生成 {$generated} 条 {$type} 类型的通知！");
        }

        // 显示统计信息
        if ($type === Notification::TYPE_USER) {
            // 用户消息统计
            $userStats = Notification::user($userId)
                ->selectRaw('category, COUNT(*) as count, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
                ->groupBy('category')
                ->get();

            if ($userStats->isNotEmpty()) {
                $this->newLine();
                $this->info("用户消息统计：");
                $tableData = $userStats->map(function ($stat) {
                    return [
                        'category' => $stat->category,
                        'count' => $stat->count,
                        'unread' => $stat->unread_count,
                        'read' => $stat->count - $stat->unread_count,
                    ];
                })->toArray();

                $this->table(
                    ['分类', '总数', '未读', '已读'],
                    $tableData
                );
            }

            // 系统消息统计（如果生成了系统消息）
            if ($systemCount > 0) {
                $systemStats = Notification::system()
                    ->selectRaw('category, COUNT(*) as count, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
                    ->groupBy('category')
                    ->get();

                if ($systemStats->isNotEmpty()) {
                    $this->newLine();
                    $this->info("系统消息统计：");
                    $tableData = $systemStats->map(function ($stat) {
                        return [
                            'category' => $stat->category,
                            'count' => $stat->count,
                            'unread' => $stat->unread_count,
                            'read' => $stat->count - $stat->unread_count,
                        ];
                    })->toArray();

                    $this->table(
                        ['分类', '总数', '未读', '已读'],
                        $tableData
                    );
                }
            }
        } else {
            // 系统消息统计
            $stats = Notification::system()
                ->selectRaw('category, COUNT(*) as count, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
                ->groupBy('category')
                ->get();

            if ($stats->isNotEmpty()) {
                $this->newLine();
                $this->info("通知统计：");
                $tableData = $stats->map(function ($stat) {
                    return [
                        'category' => $stat->category,
                        'count' => $stat->count,
                        'unread' => $stat->unread_count,
                        'read' => $stat->count - $stat->unread_count,
                    ];
                })->toArray();

                $this->table(
                    ['分类', '总数', '未读', '已读'],
                    $tableData
                );
            }
        }

        return Command::SUCCESS;
    }

    /**
     * 生成通知数据
     *
     * @param string $category
     * @param array $config
     * @param int $index
     * @return array
     */
    protected function generateNotificationData(string $category, array $config, int $index): array
    {
        $title = $config['title'];
        $contentTemplate = $config['content_template'];
        $dataTemplate = $config['data_template'];

        // 生成动态数据
        $data = [];
        $replacements = [];

        foreach ($dataTemplate as $key => $value) {
            if (is_numeric($value)) {
                // 如果是数字，生成随机值
                $randomValue = is_float($value) 
                    ? round($value * (0.5 + rand(0, 100) / 100), 2)
                    : rand((int)($value * 0.5), (int)($value * 1.5));
                $data[$key] = $randomValue;
                $replacements['{' . $key . '}'] = $randomValue;
            } elseif (str_contains($value, '{id}')) {
                // 如果包含 {id}，替换为索引
                $data[$key] = str_replace('{id}', str_pad($index, 6, '0', STR_PAD_LEFT), $value);
                $replacements['{' . $key . '}'] = $data[$key];
            } else {
                $data[$key] = $value;
                $replacements['{' . $key . '}'] = $value;
            }
        }

        // 替换内容模板中的占位符
        $content = str_replace(array_keys($replacements), array_values($replacements), $contentTemplate);

        return [
            'title' => $title,
            'content' => $content,
            'data' => $data,
        ];
    }
}
