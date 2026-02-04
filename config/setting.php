<?php

return [
    [
        'group' => 'referral',
        'key' => 'deposit_bonus_starter',
        'value' => [
            'method' => 'fixed',
            'currency' => 'USD',
            'deposit_min_amount' => 100,
            'bonus_amount' => 20,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'referral',
        'key' => 'deposit_bonus_advanced',
        'value' => [
            'method' => 'fixed',
            'currency' => 'USD',
            'deposit_min_amount' => 1000,
            'bonus_amount' => 80,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'referral',
        'key' => 'commission_bonus',
        'value' => [
            'method' => 'ratio',
            'currency' => 'USD',
            'ratio' => 30,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'referral',
        'key' => 'vip_upgrade_bonus',
        'value' => [
            'method' => 'fixed',
            'currency' => 'USD',
            'levels' => [
                '1' => 0.25,
                '2' => 0.25,
                '3' => 0.25,
            ],
            'enabled' => true,
        ],
    ],
    [
        'group' => 'promotion',
        'key' => 'check_in_bonus',
        'value' => [
            'method' => 'fixed',
            'currency' => 'USD',
            'rewards' => [0.25, 0.25, 0.25, 0.25, 0.25, 0.25, 0.25],
            'enabled' => true,
            'payment_method_ids' => [], // 可以触发额外签到的支付通道 ID 列表，例如：[1, 2, 3]
        ],
    ],
    [
        'group' => 'promotion',
        'key' => 'first_deposit_bonus',
        'value' => [
            'method' => 'ratio',
            'currency' => 'USD',
            'amounts' => [10, 20, 30],
            'ratio' => [100, 120, 140],
            'max_bonus_amount' => 100,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'promotion',
        'key' => 'second_deposit_bonus',
        'value' => [
            'method' => 'ratio',
            'currency' => 'USD',
            'amounts' => [10, 20, 30],
            'ratio' => [100, 140, 160],
            'max_bonus_amount' => 100,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'promotion',
        'key' => 'third_deposit_bonus',
        'value' => [
            'method' => 'ratio',
            'currency' => 'USD',
            'amounts' => [10, 20, 30],
            'ratio' => [100, 160, 180],
            'max_bonus_amount' => 100,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'promotion',
        'key' => 'daily_deposit_bonus',
        'value' => [
            'method' => 'ratio',
            'currency' => 'USD',
            'amounts' => [10, 20, 30],
            'ratio' => [10, 20, 30],
            'max_bonus_amount' => 100,
            'times' => 3,
            'enabled' => true,
        ],
    ],
    [
        'group' => 'vip',
        'key' => 'vip',
        'value' => [
            'supported_game_categories' => ['slot'],
            'weekly_cashback' => [
                'enabled' => true,
            ],
            'monthly_cashback' => [
                'enabled' => true,
            ],
        ],
    ],
];