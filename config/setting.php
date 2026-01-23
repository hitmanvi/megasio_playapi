<?php

return [
    
    'promotion' => [
        'welcome_bonus' => [
            'method' => 'fixed',
            'amount' => 20
        ],
        'fisrt_deposit_bonus' => [
            'method' => 'ratio',
            'amounts' => [10, 20, 30],
            'ratio' => [100, 120, 140],
            'max_amount' => 1000,             
        ],
    ],
];