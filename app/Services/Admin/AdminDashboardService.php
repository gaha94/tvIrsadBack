<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function execute(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $ordersPending = Order::where('status', 'pending')->count();
        $ordersConfirmed = Order::where('status', 'confirmed')->count();
        $ordersPaid = Order::where('status', 'paid')->count();
        $ordersCancelled = Order::where('status', 'cancelled')->count();

        $salesToday = (float) Order::whereIn('status', ['confirmed', 'paid'])
            ->whereDate('created_at', $today)
            ->sum('total');

        $salesMonth = (float) Order::whereIn('status', ['confirmed', 'paid'])
            ->whereDate('created_at', '>=', $monthStart)
            ->sum('total');

        $stockSub = DB::table('product_stocks')
            ->selectRaw('product_id, SUM(stock) as stock_total, SUM(reserved_stock) as reserved_total')
            ->groupBy('product_id');

        $productsOutOfStock = DB::query()
            ->fromSub($stockSub, 'ps')
            ->whereRaw('(COALESCE(ps.stock_total, 0) - COALESCE(ps.reserved_total, 0)) <= 0')
            ->count();

        $productsLowStock = DB::query()
            ->fromSub($stockSub, 'ps')
            ->whereRaw('(COALESCE(ps.stock_total, 0) - COALESCE(ps.reserved_total, 0)) > 0')
            ->whereRaw('(COALESCE(ps.stock_total, 0) - COALESCE(ps.reserved_total, 0)) <= 5')
            ->count();

        $recentOrders = Order::query()
            ->orderByDesc('id')
            ->limit(10)
            ->get([
                'id',
                'order_number',
                'status',
                'payment_status',
                'customer_name',
                'total',
                'created_at',
            ]);

        $recentStockMovements = StockMovement::with([
                'product:id,code,name,slug',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'stats' => [
                'orders_pending' => $ordersPending,
                'orders_confirmed' => $ordersConfirmed,
                'orders_paid' => $ordersPaid,
                'orders_cancelled' => $ordersCancelled,
                'sales_today' => round($salesToday, 2),
                'sales_month' => round($salesMonth, 2),
                'products_out_of_stock' => $productsOutOfStock,
                'products_low_stock' => $productsLowStock,
            ],
            'recent_orders' => $recentOrders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'customer_name' => $order->customer_name,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                ];
            })->values(),
            'recent_stock_movements' => $recentStockMovements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'note' => $movement->note,
                    'product' => $movement->product ? [
                        'id' => $movement->product->id,
                        'code' => $movement->product->code,
                        'name' => $movement->product->name,
                        'slug' => $movement->product->slug,
                    ] : null,
                    'created_at' => $movement->created_at,
                ];
            })->values(),
        ];
    }
}