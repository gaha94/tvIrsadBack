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
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminBrandController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminOrderNoteController;

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

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);

    // Brands
    Route::get('/admin/brands', [AdminBrandController::class, 'index']);
    Route::post('/admin/brands', [AdminBrandController::class, 'store']);
    Route::get('/admin/brands/{id}', [AdminBrandController::class, 'show']);
    Route::put('/admin/brands/{id}', [AdminBrandController::class, 'update']);
    Route::patch('/admin/brands/{id}/status', [AdminBrandController::class, 'updateStatus']);

    // Categories
    Route::get('/admin/categories', [AdminCategoryController::class, 'index']);
    Route::post('/admin/categories', [AdminCategoryController::class, 'store']);
    Route::get('/admin/categories/{id}', [AdminCategoryController::class, 'show']);
    Route::put('/admin/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::patch('/admin/categories/{id}/status', [AdminCategoryController::class, 'updateStatus']);

    // Orders
    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    Route::get('/admin/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/admin/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/admin/orders/expire-pending', [AdminOrderMaintenanceController::class, 'expirePending']);
    Route::get('/admin/orders/{id}/notes', [AdminOrderNoteController::class, 'index']);
    Route::post('/admin/orders/{id}/notes', [AdminOrderNoteController::class, 'store']);

    // Products
    Route::get('/admin/products', [AdminProductController::class, 'index']);
    Route::post('/admin/products', [AdminProductController::class, 'store']);
    Route::get('/admin/products/{id}', [AdminProductController::class, 'show']);
    Route::put('/admin/products/{id}', [AdminProductController::class, 'update']);
    Route::patch('/admin/products/{id}/status', [AdminProductController::class, 'updateStatus']);

    // Product stock
    Route::get('/admin/products/{id}/stock', [AdminProductStockController::class, 'show']);
    Route::put('/admin/products/{id}/stock', [AdminProductStockController::class, 'update']);

    // Product images
    Route::get('/admin/products/{id}/images', [AdminProductImageController::class, 'index']);
    Route::post('/admin/products/{id}/images', [AdminProductImageController::class, 'store']);
    Route::patch('/admin/products/{id}/images/{imageId}/main', [AdminProductImageController::class, 'setMain']);
    Route::delete('/admin/products/{id}/images/{imageId}', [AdminProductImageController::class, 'destroy']);

    // Stock movements
    Route::get('/admin/stock-movements', [AdminStockMovementController::class, 'index']);
});