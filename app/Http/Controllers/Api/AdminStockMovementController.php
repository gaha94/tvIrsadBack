<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class AdminStockMovementController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'type' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 20;

        $query = StockMovement::query()->with([
            'product:id,code,name,slug',
            'warehouse:id,name,code',
            'admin:id,name,email',
        ]);

        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (!empty($validated['type'])) {
            $query->where('type', trim($validated['type']));
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $movements = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'current_page' => $movements->currentPage(),
            'data' => collect($movements->items())->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'stock_before' => $movement->stock_before,
                    'stock_after' => $movement->stock_after,
                    'reserved_before' => $movement->reserved_before,
                    'reserved_after' => $movement->reserved_after,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'note' => $movement->note,
                    'product' => $movement->product ? [
                        'id' => $movement->product->id,
                        'code' => $movement->product->code,
                        'name' => $movement->product->name,
                        'slug' => $movement->product->slug,
                    ] : null,
                    'warehouse' => $movement->warehouse ? [
                        'id' => $movement->warehouse->id,
                        'name' => $movement->warehouse->name,
                        'code' => $movement->warehouse->code,
                    ] : null,
                    'admin' => $movement->admin ? [
                        'id' => $movement->admin->id,
                        'name' => $movement->admin->name,
                        'email' => $movement->admin->email,
                    ] : null,
                    'created_at' => $movement->created_at,
                ];
            })->values(),
            'last_page' => $movements->lastPage(),
            'per_page' => $movements->perPage(),
            'total' => $movements->total(),
        ]);
    }
}