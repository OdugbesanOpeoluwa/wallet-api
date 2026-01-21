<?php

return [
    'transfer' => [
        'daily_limit' => env('TRANSFER_DAILY_LIMIT', 1000000),
        'per_transaction_limit' => env('TRANSFER_PER_TXN_LIMIT', 500000),
        'velocity' => [
            'max_count' => 10,
            'window_minutes' => 60,
        ],
    ],

    'withdrawal' => [
        'daily_limit' => env('WITHDRAWAL_DAILY_LIMIT', 500000),
        'per_transaction_limit' => env('WITHDRAWAL_PER_TXN_LIMIT', 200000),
        'velocity' => [
            'max_count' => 3,
            'window_minutes' => 60,
        ],
        'cooldown_after_password_change' => 24,
    ],

    'bill' => [
        'daily_limit' => env('BILL_DAILY_LIMIT', 100000),
        'velocity' => [
            'max_count' => 20,
            'window_minutes' => 60,
        ],
    ],
];