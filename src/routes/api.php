<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashierTransactionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Owner\AssignDriverController;
use App\Http\Controllers\Api\Owner\DashboardController;
use App\Http\Controllers\Api\Owner\UserManagementController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn () => response()->json([
        'success' => true,
        'message' => 'Kanzza Backend API aktif.',
        'timestamp' => now()->toISOString(),
    ]));

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::post('/payments/midtrans/notification', [PaymentController::class, 'notification']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);

        Route::middleware('role:customer')->group(function (): void {
            Route::apiResource('addresses', AddressController::class);

            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

            Route::post('/orders/{order}/payment', [PaymentController::class, 'createOrReuse']);
            Route::post('/orders/{order}/payment/check', [PaymentController::class, 'checkStatus']);
        });

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        Route::middleware('role:cashier,owner')->group(function (): void {
            Route::get('/cashier/transactions', [CashierTransactionController::class, 'index']);
            Route::post('/cashier/transactions', [CashierTransactionController::class, 'store']);

            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::post('/orders/{order}/assign-driver', AssignDriverController::class);
        });

        Route::middleware('role:driver')->prefix('driver')->group(function (): void {
            Route::get('/deliveries', [DeliveryController::class, 'index']);
            Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
            Route::patch('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
        });

        Route::middleware('role:owner')->prefix('owner')->group(function (): void {
            Route::get('/dashboard', DashboardController::class);

            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::patch('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

            Route::post('/products', [ProductController::class, 'store']);
            Route::post('/products/{product}', [ProductController::class, 'update']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::patch('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole']);
            Route::patch('/users/{user}/status', [UserManagementController::class, 'updateStatus']);
        });
    });
});
