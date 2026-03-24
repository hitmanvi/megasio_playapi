<?php

namespace App\Services;

use App\Models\Transaction;

class UserService
{
    /**
     * Transaction::TYPE_* => 对外分类 key
     */
    protected const REWARD_CATEGORY_BY_TYPE = [
        Transaction::TYPE_CHECK_IN_REWARD => 'check_in',
        Transaction::TYPE_BONUS_TASK_REWARD => 'bonus_task',
        Transaction::TYPE_INVITATION_REWARD => 'invitation',
        Transaction::TYPE_VIP_LEVEL_UP_REWARD => 'vip_level_up',
        Transaction::TYPE_WEEKLY_CASHBACK => 'weekly_cashback',
    ];

    /**
     * 用户各类奖励入账总额（来自已完成流水，按币种汇总）
     *
     * @return array{
     *     categories: array<string, array<string, float>>,
     *     total_by_currency: array<string, float>
     * }
     */
    public function getRewardTotalsByCategory(int $userId): array
    {
        $types = Transaction::rewardCreditTypes();

        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereIn('type', $types)
            ->selectRaw('type, currency, SUM(amount) as total')
            ->groupBy('type', 'currency')
            ->get();

        $categories = [];
        foreach (self::REWARD_CATEGORY_BY_TYPE as $type => $categoryKey) {
            $categories[$categoryKey] = [];
        }

        $totalByCurrency = [];

        foreach ($rows as $row) {
            $categoryKey = self::REWARD_CATEGORY_BY_TYPE[$row->type] ?? null;
            if ($categoryKey === null) {
                continue;
            }

            $currency = strtoupper((string) $row->currency);
            $amount = (float) $row->total;

            if (!isset($categories[$categoryKey][$currency])) {
                $categories[$categoryKey][$currency] = 0.0;
            }
            $categories[$categoryKey][$currency] += $amount;

            if (!isset($totalByCurrency[$currency])) {
                $totalByCurrency[$currency] = 0.0;
            }
            $totalByCurrency[$currency] += $amount;
        }

        foreach ($categories as $key => $byCurrency) {
            ksort($categories[$key]);
        }
        ksort($totalByCurrency);

        return [
            'categories' => $categories,
            'total_by_currency' => $totalByCurrency,
        ];
    }
}
