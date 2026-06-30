<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Public Guest Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/stores/{id}', [StoreController::class, 'show']);

// Authenticated Routes
Route::middleware('auth.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Vouchers & Promos (General authenticated endpoints)
    Route::get('/vouchers', [DiscountController::class, 'listVouchers']);
    Route::get('/promos', [DiscountController::class, 'listPromos']);
    Route::get('/vouchers/{code}', [DiscountController::class, 'showVoucher']);
    Route::get('/promos/{code}', [DiscountController::class, 'showPromo']);
    Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

    // Admin Specific Routes (can be accessed by admin role)
    Route::middleware('role:Admin')->group(function () {
        Route::post('/admin/vouchers', [DiscountController::class, 'createVoucher']);
        Route::post('/admin/promos', [DiscountController::class, 'createPromo']);
        
        Route::get('/admin/dashboard', [ReportController::class, 'adminDashboard']);
        Route::get('/admin/orders', [ReportController::class, 'adminOrders']);
        Route::get('/admin/delivery-jobs', [ReportController::class, 'adminDeliveryJobs']);
        Route::post('/admin/process-overdue', [OrderController::class, 'processOverdue']);
        Route::post('/admin/simulate-next-day', [OrderController::class, 'simulateNextDay']);
    });

    // Buyer Specific Routes
    Route::middleware('role:Buyer')->group(function () {
        // Wallet
        Route::get('/wallet', [WalletController::class, 'index']);
        Route::post('/wallet/topup', [WalletController::class, 'topup']);

        // Addresses
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

        // Cart
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);

        // Checkout
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/orders/buyer', [OrderController::class, 'buyerOrders']);
        Route::get('/reports/buyer', [ReportController::class, 'buyerReport']);
    });

    // Seller Specific Routes
    Route::middleware('role:Seller')->group(function () {
        Route::post('/store', [StoreController::class, 'store']);
        Route::get('/store/my', [StoreController::class, 'myStore']);

        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Incoming orders
        Route::get('/orders/seller', [OrderController::class, 'sellerOrders']);
        Route::post('/orders/{id}/process', [OrderController::class, 'processOrder']);
        Route::get('/reports/seller', [ReportController::class, 'sellerReport']);
    });

    // Driver Specific Routes
    Route::middleware('role:Driver')->group(function () {
        Route::get('/driver/jobs', [DeliveryController::class, 'availableJobs']);
        Route::get('/driver/jobs/{orderId}', [DeliveryController::class, 'show']);
        Route::post('/driver/jobs/{orderId}/take', [DeliveryController::class, 'takeJob']);
        Route::post('/driver/jobs/{orderId}/complete', [DeliveryController::class, 'completeJob']);
        Route::get('/driver/my-jobs', [DeliveryController::class, 'myJobs']);
        Route::get('/driver/earnings', [DeliveryController::class, 'earnings']);
    });

    // Order detail route accessible by authorized parties (placed at bottom to prevent wildcard matching issues)
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']);
});
