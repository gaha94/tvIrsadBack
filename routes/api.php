<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminOrderMaintenanceController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminProductImageController;
use App\Http\Controllers\Api\AdminProductStockController;
use App\Http\Controllers\Api\AdminStockMovementController;

Route::get('/hola', function () {
    return response()->json([
        'message' => 'Hola desde Laravel',
        'status' => true,
    ]);
});

Route::get('/test-db', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'ok' => true,
            'message' => 'Conexión a MySQL correcta'
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/filters', [FilterController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/slug/{slug}', [ProductController::class, 'showBySlug']);
Route::get('/products/{id}', [ProductController::class, 'show']);

// Públicas
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/number/{orderNumber}', [OrderController::class, 'showByNumber']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::middleware('admin.auth')->group(function () {
    Route::get('/admin/me', [AdminAuthController::class, 'me']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    Route::get('/admin/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/admin/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::post('/admin/orders/expire-pending', [AdminOrderMaintenanceController::class, 'expirePending']);

    Route::get('/admin/products', [AdminProductController::class, 'index']);
    Route::post('/admin/products', [AdminProductController::class, 'store']);
    Route::get('/admin/products/{id}', [AdminProductController::class, 'show']);
    Route::put('/admin/products/{id}', [AdminProductController::class, 'update']);
    Route::patch('/admin/products/{id}/status', [AdminProductController::class, 'updateStatus']);

    Route::get('/admin/products/{id}/stock', [AdminProductStockController::class, 'show']);
    Route::put('/admin/products/{id}/stock', [AdminProductStockController::class, 'update']);

    Route::get('/admin/products/{id}/images', [AdminProductImageController::class, 'index']);
    Route::post('/admin/products/{id}/images', [AdminProductImageController::class, 'store']);
    Route::patch('/admin/products/{id}/images/{imageId}/main', [AdminProductImageController::class, 'setMain']);
    Route::delete('/admin/products/{id}/images/{imageId}', [AdminProductImageController::class, 'destroy']);

    Route::get('/admin/stock-movements', [AdminStockMovementController::class, 'index']);
});