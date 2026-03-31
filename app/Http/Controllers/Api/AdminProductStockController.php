<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductStockRequest;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\Stock\StockMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductStockController extends Controller
{
    public function __construct(
        protected StockMovementService $stockMovementService
    ) {}

    public function show(int $id)
    {
        $product = Product::with([
            'stocks',
            'brand:id,name,slug',
            'category:id,name,slug',
        ])->findOrFail($id);

        $stockRow = $product->stocks
            ->sortBy('warehouse_id')
            ->first();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
            ],
            'stock' => $stockRow ? [
                'warehouse_id' => $stockRow->warehouse_id,
                'stock' => (int) $stockRow->stock,
                'reserved_stock' => (int) $stockRow->reserved_stock,
                'available_stock' => max((int) $stockRow->stock - (int) $stockRow->reserved_stock, 0),
                'updated_at' => $stockRow->updated_at,
            ] : null,
        ]);
    }

    public function update(UpdateProductStockRequest $request, int $id)
    {
        $validated = $request->validated();

        $product = Product::findOrFail($id);

        $stockRow = DB::transaction(function () use ($product, $validated) {
            $stockRow = ProductStock::where('product_id', $product->id)
                ->orderBy('warehouse_id', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$stockRow) {
                throw ValidationException::withMessages([
                    'product' => ['No se encontró registro de stock para este producto.'],
                ]);
            }

            $stockBefore = (int) $stockRow->stock;
            $reservedBefore = (int) $stockRow->reserved_stock;

            $newStock = (int) $validated['stock'];
            $newReservedStock = array_key_exists('reserved_stock', $validated)
                ? (int) $validated['reserved_stock']
                : (int) $stockRow->reserved_stock;

            if ($newReservedStock > $newStock) {
                throw ValidationException::withMessages([
                    'reserved_stock' => ['La reserva no puede ser mayor que el stock total.'],
                ]);
            }

            $stockRow->stock = $newStock;
            $stockRow->reserved_stock = $newReservedStock;
            $stockRow->updated_at = now();
            $stockRow->save();

            $admin = request()->attributes->get('admin');

            $this->stockMovementService->log([
                'product_id' => $product->id,
                'warehouse_id' => $stockRow->warehouse_id,
                'type' => 'manual_set',
                'quantity' => 0,
                'stock_before' => $stockBefore,
                'stock_after' => (int) $stockRow->stock,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (int) $stockRow->reserved_stock,
                'reference_type' => 'admin_manual',
                'reference_id' => null,
                'note' => 'Ajuste manual de stock',
                'admin_id' => $admin?->id,
            ]);

            return $stockRow;
        });

        return response()->json([
            'message' => 'Stock actualizado correctamente.',
            'product_id' => $product->id,
            'stock' => [
                'warehouse_id' => $stockRow->warehouse_id,
                'stock' => (int) $stockRow->stock,
                'reserved_stock' => (int) $stockRow->reserved_stock,
                'available_stock' => max((int) $stockRow->stock - (int) $stockRow->reserved_stock, 0),
                'updated_at' => $stockRow->updated_at,
            ],
        ]);
    }
}