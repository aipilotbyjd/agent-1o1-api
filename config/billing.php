<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credit Pack Catalog
    |--------------------------------------------------------------------------
    |
    | One-off, non-recurring credit purchases. Each pack must correspond to a
    | Price created ahead of time in the Stripe dashboard — Cashier's checkout
    | method warns against creating ad-hoc prices per checkout.
    |
    */
    'packs' => [
        'credits_1000' => [
            'credits' => 1000,
            'price_cents' => 1000,
            'stripe_price_id' => env('STRIPE_PACK_CREDITS_1000_PRICE_ID'),
        ],
        'credits_5000' => [
            'credits' => 5000,
            'price_cents' => 4500,
            'stripe_price_id' => env('STRIPE_PACK_CREDITS_5000_PRICE_ID'),
        ],
        'credits_25000' => [
            'credits' => 25000,
            'price_cents' => 20000,
            'stripe_price_id' => env('STRIPE_PACK_CREDITS_25000_PRICE_ID'),
        ],
    ],

];
