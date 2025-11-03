<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Charges Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the platform charges for different vehicle types
    | as per the GAUVA business logic.
    |
    */

    'bike' => [
        'per_trip_fee' => 5,
        'daily_fee' => 7, // Deducted after first trip of the day
        'insurance' => [
            'customer' => 1,
            'driver' => 1,
            'total' => 2,
        ],
        'cashback' => [
            'enabled' => true,
            'percent' => 10,
            'max_amount' => 5,
        ],
        'wallet' => [
            'use_limit_per_ride' => 5,
        ],
    ],

    'auto' => [
        'per_trip_fee' => 3,
        'daily_fee' => 11, // Deducted after first trip of the day
        'insurance' => [
            'customer' => 1,
            'driver' => 1,
            'total' => 2,
        ],
        'cashback' => [
            'enabled' => false,
        ],
        'bonus' => [
            'enabled' => true,
            'trip_threshold' => 20,
            'amount' => 50,
        ],
    ],

    'car' => [
        'per_trip_fee' => 11,
        'daily_fee' => 0, // No daily fee for car
        'day_pass' => [
            'enabled' => true,
            'amount' => 55,
            'unlimited_trips' => true,
        ],
        'insurance' => [
            'customer' => 2,
            'driver' => 2,
            'total' => 4,
        ],
        'cashback' => [
            'enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Rules
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'daily_fee_deduction' => [
            'trigger' => 'first_completed_trip',
            'frequency' => 'once_per_day',
            'condition' => 'if_driver_completes_trip',
        ],
        'cashback_logic' => [
            'calculate_first' => true,
            'add_to_wallet' => true,
            'usage_limit' => 5,
        ],
        'driver_wallet' => [
            'handles' => [
                'daily_fee_deductions',
                'per_trip_platform_fee',
                'insurance',
                'driver_bonus',
                'day_pass',
            ],
        ],
    ],
];
