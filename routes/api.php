<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\AdminController;
use App\Http\Controllers\API\V1\OrderController;
use App\Http\Controllers\API\V1\PaymentController;
use App\Http\Controllers\API\V1\ProductController;
use App\Http\Controllers\API\V1\UserController;
use App\Http\Controllers\API\V1\DealsController;
use App\Http\Controllers\API\V1\FinanceController;
use App\Http\Controllers\API\V1\DeliveryController;
//import admin middleware
use App\Http\Middleware\Admin;
use App\Http\Middleware\Optional;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('v1')->group(callback: function () {
    // User Authentication
    Route::prefix('user')->group(function () {
        Route::post('/register', [AuthController::class, 'createAccount'])->name('auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/social-auth', [AuthController::class, 'socialAuth'])->name('auth.social-auth');
        Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('auth.forgot-password');
        Route::post('/resend-otp', [AuthController::class, 'sendOTP'])->name('auth.resend-otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('auth.verify-otp');
        Route::post('/verify-id', [AuthController::class, 'verifyId']);
        Route::post('/verify-username', [AuthController::class, 'verifyUsername']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/verify-refcode', [AuthController::class, 'verifyReferralCode']);



        Route::prefix('finance')->group(function () {
            Route::get('/all-currency', [FinanceController::class, 'getAllCurrency'])->name('exchange-rate.all-currency');
        });

    });



    Route::middleware([Optional::class])->prefix('user')->group(function () {
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'getAllProducts']);
            Route::get('/get-products/{id?}', [ProductController::class, 'getProduct']);
            Route::get('/get-types', [ProductController::class, 'getTypes']);
            Route::post('/confirm-price', [ProductController::class, 'confirmPrice']);
            Route::get('/filter', [ProductController::class, 'index']);
            Route::get('/category-shop', [ProductController::class, 'getProductByCategory']);
            Route::get('/best-seller', [ProductController::class, 'bestSeller']);
            Route::get('handleImages', [DealsController::class, 'handleImages']);
        });

        Route::prefix('orders')->group(function () {
            Route::post('/', [OrderController::class, 'placeOrder']);
            Route::get('/history', [OrderController::class, 'getOrderHistory']);
            // getOrderDistribution
        });

        Route::prefix('payment')->group(function () {
            Route::prefix('stripe')->group(function () {
                Route::post('pay', [PaymentController::class, 'pay']);
                Route::get('confirm', [PaymentController::class, 'confirmPayment']);
                Route::post('webhook', [PaymentController::class, 'webhook']);
            });

            Route::prefix('paystack')->group(function () {
                Route::post('/initiate', [PaymentController::class, 'initiatePayment']);
                Route::get('/verify', [PaymentController::class, 'verifyPayment']);
            });

            Route::prefix('flutterwave')->group(function () {
                Route::post('initiate', [PaymentController::class, 'startPayment']);
                Route::get('verify', [PaymentController::class, 'checkPayment']);
                Route::post('webhook', [PaymentController::class, 'webhook']);
            });
        });

        Route::prefix('deals')->group(function () {
            Route::get('types', [DealsController::class, 'getDealTypes']);
            Route::post('add-product', [DealsController::class, 'addProductDeal']);
            Route::delete('clear-product', [DealsController::class, 'clearProductDeal']);
            Route::get('get-offers/{dealType?}', [DealsController::class, 'getSpecialDeals']);
            Route::post('create-deal-type/{slug?}', [DealsController::class, 'createOrUpdateDealType']);
            Route::delete('delete-deal-type/{id?}', [DealsController::class, 'deleteDealTypes']);
        });
    });




    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('user.logout');
        // Authenticated user routes
        Route::prefix('user')->group(function () {
            Route::post('/change-password', [AuthController::class, 'changePassword'])->name('user.change-password');
            Route::post('/update-password', [AuthController::class, 'updatePassword'])->name('auth.update-password');
            Route::post('/logout', [AuthController::class, 'logout'])->name('user.logout');
            Route::delete('/close-account', [AuthController::class, 'deleteAccount'])->name('user.close-account');

            // User dashboard routes
            Route::get('/', [UserController::class, 'getUser'])->name('user.get');
            Route::post('/edit-profile', [UserController::class, 'editProfile'])->name('user.edit-profile');
            Route::get('/get-notification', [UserController::class, 'getNotification'])->name('user.get-notification');
            Route::get('/change-notification-status/{id}', [UserController::class, 'changeNotificationStatus']);
            Route::post('/shipping-address', [UserController::class, 'address']);

            Route::get('/check-discount-promo', [UserController::class, 'checkDiscountCode'])->name('user.check-discount-promo');


            Route::prefix('payment')->group(function () {
                Route::get('/get-receiver/{levelId}', [PaymentController::class, 'getReceiverAcct']);
                Route::get('/initiate', [PaymentController::class, 'initiateTransaction']);
                Route::get('/confirm-payment', [PaymentController::class, 'confirmPayment']);
                Route::get('/get-payment-history/{type?}', [PaymentController::class, 'getTransactionHistory']);
            });


            // Route::prefix('products')->group(function () {
            //     Route::post('/', [ProductController::class, 'store']);
            //     // Route::get('/', [ProductController::class, 'getAllProducts']);
            //     Route::put('/{id}', [ProductController::class, 'update']);
            //     Route::delete('/{id}', [ProductController::class, 'destroy']);
            //     // Route::get('/get-products/{id?}', [ProductController::class, 'getProduct']);
            //     // Route::get('/get-types', [ProductController::class, 'getTypes']);
            //     // Route::post('/confirm-price', [ProductController::class, 'confirmPrice']);
            //     // Route::get('/filter', [ProductController::class, 'index']);
            // });

            //payment rout prefix
            // Route::prefix('payment')->group(function () {
            //     Route::post('wallet/deposit', [PaymentController::class, 'pay']);
            //     Route::get('stripe/confirm', [PaymentController::class, 'confirmPayment']);
            //     Route::post('/webhook/stripe', [PaymentController::class, 'webhook']);
            // });

        });


        Route::middleware([Admin::class])->prefix('admin')->group(function () {
            Route::prefix('products')->group(function () {
                Route::post('/', [ProductController::class, 'store']);
                // Route::get('/', [ProductController::class, 'getAllProducts']);
                Route::put('/{id}', [ProductController::class, 'update']);
                Route::delete('/{id}', [ProductController::class, 'destroy']);
                Route::get('/distribution', [ProductController::class, 'getProductDistribution']);
                // Route::get('/get-products/{id?}', [ProductController::class, 'getProduct']);
                // Route::get('/get-types', [ProductController::class, 'getTypes']);
                // Route::post('/confirm-price', [ProductController::class, 'confirmPrice']);
                // Route::get('/filter', [ProductController::class, 'index']);
            });

            Route::prefix('settings')->group(function () {
                Route::post('/', [AdminController::class, 'adminSettings']);
                Route::get('/{id?}', [AdminController::class, 'getAdminSettings']);
                Route::put('/{id}', [AdminController::class, 'updateAdminSetting']);
                Route::delete('/{id}', [AdminController::class, 'deleteAdminSetting']);
            });

            Route::prefix('orders')->group(function () {
                Route::get('/distribution', [OrderController::class, 'getOrderDistribution']);
                Route::get('/list', [OrderController::class, 'getAllOrders']);
                //updateOrderStatus
                Route::put('/update-status/{orderId}', [OrderController::class, 'updateOrderStatus']);
                //get all orders

            });

            Route::prefix('customer')->group(function () {
                Route::get('/activities/{id?}', [AdminController::class, 'getUserActivities'])->name('admin.get-users-activity');
                Route::get('/metadata', [AdminController::class, 'getUserMetadata'])->name('admin.get-users-metadata');
                Route::get('/{id?}', [AdminController::class, 'getUsers'])->name('admin.get-user');
                Route::delete('/delete/{id}', [AdminController::class, 'deleteUser'])->name('user.close-account');
                Route::post('/restore/{id}', [AdminController::class, 'restoreUser'])->name('user.restore-account');
                Route::post('/suspend/{id}', [AdminController::class, 'suspendUser'])->name('user.suspend-account');
                Route::get('/analytics-month-chart', [AdminController::class, 'revenueAnalyticsChart']);
                Route::get('/payment/get-transactions', [AdminController::class, 'getTransactions']);
                Route::get('/payment/change-status', [AdminController::class, 'changeTransactionStatus']);
            });

            Route::prefix('promo')->group(function () {
                Route::post('/', [AdminController::class, 'createAdminPromo'])->name('admin-promo.create');
                Route::post('/{id}', [AdminController::class, 'updateAdminPromo'])->name('admin-promo.update');
                Route::get('/{id?}', [AdminController::class, 'getAdminPromo'])->name('admin-promo.get');
                Route::delete('/{id}', [AdminController::class, 'deleteAdminPromo'])->name('admin-promo.delete');
            });
        });
    });

    // Fez Delivery Routes
    Route::prefix('delivery')->group(function () {
        Route::post('/authenticate', [DeliveryController::class, 'authenticate']);
        Route::post('/calculate-fee', [DeliveryController::class, 'calculateDeliveryFee']);
        Route::post('/create-order', [DeliveryController::class, 'createDeliveryOrder']);
        Route::get('/order-status/{orderId}', [DeliveryController::class, 'getOrderStatus']);
        Route::post('/calculate-cost', [DeliveryController::class, 'calculateDeliveryCost']);
        Route::get('/order/{order_id}', [DeliveryController::class, 'getDeliveryOrder']);
        Route::post('/search', [DeliveryController::class, 'searchOrders']);
        Route::get('/track/{orderNumber}', [DeliveryController::class, 'trackOrder']);
        Route::put('/update-order', [DeliveryController::class, 'updateDeliveryOrder']);
        Route::post('/calculate-time', [DeliveryController::class, 'calculateDeliveryTime']);
        Route::get('/export-locations', [DeliveryController::class, 'getExportLocations']);
        Route::post('/export-cost', [DeliveryController::class, 'calculateExportCost']);
        Route::post('/create-export-order', [DeliveryController::class, 'createExportOrder']);
        Route::post('/webhook', [DeliveryController::class, 'handleWebhook']);
            // ->middleware('verify.webhook');
    });

});
