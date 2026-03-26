<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OrderController;

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

Route::post('/orders', [OrderController::class, 'store']);