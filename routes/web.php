<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


// routes/web.php

use Illuminate\Support\Facades\Env;

// Define a route to dump .env variables
Route::get('/env', function () {
    // Dump all .env variables
    dd([
        'PAYSTACK_SECRET_KEY' => env('PAYSTACK_SECRET_KEY'),
        'PAYSTACK_PAYMENT_URL' => env('PAYSTACK_PAYMENT_URL'),
        'Config PAYSTACK_SECRET_KEY' => config('services.paystack.secret_key'),
        'Config PAYSTACK_PAYMENT_URL' => config('services.paystack.payment_url'),
    ]);
});
