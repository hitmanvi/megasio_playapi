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
        ],
    ],
    [
        'group' => 'referral',
        'key' => 'commission_bonus',
        'value' => [
            'method' => 'ratio',
            'currency' => 'USD',
            'ratio' => 30,
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
            ]
        ],
    ],
    [
        'group' => 'promotion',
        'key' => 'check_in_bonus',
        'value' => [
            'method' => 'fixed',
            'currency' => 'USD',
            'rewards' => [0.25, 0.25, 0.25, 0.25, 0.25, 0.25, 0.25],
        ]
        ],
];