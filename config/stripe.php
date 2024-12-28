<?php

return [
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'public_key' => env('STRIPE_PUBLIC_KEY'),
];
