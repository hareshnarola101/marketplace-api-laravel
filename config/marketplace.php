<?php

return [

    // Single settlement currency for the whole platform (ISO 4217, upper case).
    'currency' => env('MARKETPLACE_CURRENCY', 'USD'),

    'commission' => [
        // Platform cut in basis points (1000 = 10%). Vendors can override it individually.
        'default_rate_bps' => (int) env('MARKETPLACE_COMMISSION_BPS', 1000),
    ],

    'payments' => [
        // "stripe" talks to Stripe in test mode, "fake" never leaves the process.
        'gateway' => env('PAYMENT_GATEWAY', 'fake'),

        'fake' => [
            'webhook_secret' => env('FAKE_WEBHOOK_SECRET', 'fake-webhook-secret'),
        ],
    ],

];
