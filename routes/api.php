<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\AdminController;
use App\Http\Controllers\API\V1\PaymentController;
use App\Http\Controllers\API\V1\ProductController;
use App\Http\Controllers\API\V1\UserController;

//import admin middleware
use App\Http\Middleware\Admin;

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

            Route::get('/levels/{id?}', [UserController::class, 'getLevels']);
            Route::get('/communities/{id?}', [AdminController::class, 'getCommunity']);

            // Route::post('withdrawal/request', [WithdrawalController::class, 'generateWithdrawalToken']);
            // Route::post('withdrawal/verify', [WithdrawalController::class, 'verifyWithdrawalToken']);
            // Route::post('withdrawal/update', [WithdrawalController::class, 'updateWithdrawalStatus']);

            Route::prefix('bank')->group(function () {
                Route::post('/create', [UserController::class, 'createBankDetails']);
                Route::get('/show/{id}', [UserController::class, 'showBankDetails']);
                Route::put('/update/{id}', [UserController::class, 'updateBankDetails']);
                Route::delete('/delete/{id}', [UserController::class, 'deleteBankDetails']);
                Route::get('/bank-list', [PaymentController::class, 'bankList']);
                Route::post('/verify-account-name', [PaymentController::class, 'accountName']);
            });


            Route::prefix('payment')->group(function () {
                Route::get('/get-receiver/{levelId}', [PaymentController::class, 'getReceiverAcct']);
                Route::get('/initiate', [PaymentController::class, 'initiateTransaction']);
                Route::get('/confirm-payment', [PaymentController::class, 'confirmPayment']);
                Route::get('/get-payment-history/{type?}', [PaymentController::class, 'getTransactionHistory']);
            });


            Route::prefix('products')->group(function () {
                Route::get('/own-products', [ProductController::class, 'getOwnProducts']);
                // Route::get('/', [ProductController::class, 'index']);
                Route::post('/', [ProductController::class, 'store']);
                Route::get('/{id?}', [ProductController::class, 'show']);
                Route::put('/{id}', [ProductController::class, 'update']);
                Route::delete('/{id}', [ProductController::class, 'destroy']);
                Route::get('/{id}/increment-view', [ProductController::class, 'incrementViewCount']);
                Route::get('/filter', [ProductController::class, 'filter']);
                Route::get('/{id}/generate-referral', [ProductController::class, 'generateReferralLink']);
                Route::get('refer/{referralCode}/{productId}', [ProductController::class, 'verifyReferral']);

            });

        });



        Route::middleware([Admin::class])->prefix('admin')->group(function () {
            Route::prefix('levels')->group(function () {
                Route::post('/', [AdminController::class, 'createLevel']);
                Route::get('/{id?}', [AdminController::class, 'getLevels']);
                Route::put('/{id}', [AdminController::class, 'updateLevel']);
                Route::delete('/{id}', [AdminController::class, 'deleteLevel']);
            });

            Route::prefix('settings')->group(function () {
                Route::post('/', [AdminController::class, 'adminSettings']);
                Route::get('/{id?}', [AdminController::class, 'getAdminSettings']);
                Route::put('/{id}', [AdminController::class, 'updateAdminSetting']);
                Route::delete('/{id}', [AdminController::class, 'deleteAdminSetting']);
            });

            Route::prefix('communities')->group(function () {
                Route::post('/', [AdminController::class, 'createCommunity']);
                Route::get('/{id?}', [AdminController::class, 'getCommunity']);
                Route::put('/{id}', [AdminController::class, 'updateCommunity']);
                Route::delete('/{id}', [AdminController::class, 'deleteCommunity']);
            });

            Route::prefix('products')->group(function () {
                // Route::post('/', [AdminController::class, 'createLevel']);
                // Route::get('/{id?}', [AdminController::class, 'getLevels']);
                // Route::put('/{id}', [AdminController::class, 'updateLevel']);
                // Route::delete('/{id}', [AdminController::class, 'deleteLevel']);
                Route::get('/get-products/{id?}', [AdminController::class, 'adminGetProduct']);
                Route::get('/change-approval-status/{id}', [AdminController::class, 'approveProduct']);
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
        });
    });

});
