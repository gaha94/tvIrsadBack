<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(
        protected StockMovementService $stockMovementService
    ) {}

    public function getAvailableStock(Product $product): int
    {
        $stockTotal = (int) $product->stocks->sum('stock');
        $reservedStockTotal = (int) $product->stocks->sum('reserved_stock');

        return max($stockTotal - $reservedStockTotal, 0);
    }

    public function getLockedStockRow(int $productId): ProductStock
    {
        $productStock = ProductStock::where('product_id', $productId)
            ->orderBy('warehouse_id', 'asc')
            ->lockForUpdate()
            ->first();

        if (!$productStock) {
            throw ValidationException::withMessages([
                'items' => [[
                    'product_id' => $productId,
                    'message' => 'No se encontró registro de stock para el producto.',
                ]]
            ]);
        }

        return $productStock;
    }

    public function reserveStock(int $productId, int $quantity, array $context = []): void
    {
        $productStock = $this->getLockedStockRow($productId);

        $stockBefore = (int) $productStock->stock;
        $reservedBefore = (int) $productStock->reserved_stock;
        $availableStock = max($stockBefore - $reservedBefore, 0);

        if ($availableStock < $quantity) {
            throw ValidationException::withMessages([
                'items' => [[
                    'product_id' => $productId,
                    'product_code' => $context['product_code'] ?? null,
                    'product_name' => $context['product_name'] ?? null,
                    'requested_quantity' => $quantity,
                    'available_stock' => $availableStock,
                    'message' => 'Stock insuficiente al momento de reservar.',
                ]]
            ]);
        }

        $productStock->reserved_stock = $reservedBefore + $quantity;
        $productStock->updated_at = now();
        $productStock->save();

        $this->stockMovementService->log([
            'product_id' => $productId,
            'warehouse_id' => $productStock->warehouse_id,
            'type' => 'reserve',
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => (int) $productStock->stock,
            'reserved_before' => $reservedBefore,
            'reserved_after' => (int) $productStock->reserved_stock,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'note' => $context['note'] ?? 'Reserva de stock',
            'admin_id' => $context['admin_id'] ?? null,
        ]);
    }

    public function confirmStock(int $productId, int $quantity, array $context = []): void
    {
        $productStock = $this->getLockedStockRow($productId);

        $stockBefore = (int) $productStock->stock;
        $reservedBefore = (int) $productStock->reserved_stock;

        if ($reservedBefore < $quantity) {
            throw ValidationException::withMessages([
                'items' => [[
                    'product_id' => $productId,
                    'product_code' => $context['product_code'] ?? null,
                    'product_name' => $context['product_name'] ?? null,
                    'quantity' => $quantity,
                    'reserved_stock' => $reservedBefore,
                    'message' => 'La reserva actual no alcanza para confirmar la orden.',
                ]]
            ]);
        }

        if ($stockBefore < $quantity) {
            throw ValidationException::withMessages([
                'items' => [[
                    'product_id' => $productId,
                    'product_code' => $context['product_code'] ?? null,
                    'product_name' => $context['product_name'] ?? null,
                    'quantity' => $quantity,
                    'stock' => $stockBefore,
                    'message' => 'El stock actual no alcanza para confirmar la orden.',
                ]]
            ]);
        }

        $productStock->stock = $stockBefore - $quantity;
        $productStock->reserved_stock = max($reservedBefore - $quantity, 0);
        $productStock->updated_at = now();
        $productStock->save();

        $this->stockMovementService->log([
            'product_id' => $productId,
            'warehouse_id' => $productStock->warehouse_id,
            'type' => 'confirm_discount',
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => (int) $productStock->stock,
            'reserved_before' => $reservedBefore,
            'reserved_after' => (int) $productStock->reserved_stock,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'note' => $context['note'] ?? 'Confirmación de orden',
            'admin_id' => $context['admin_id'] ?? null,
        ]);
    }

    public function releaseReservedStock(int $productId, int $quantity, array $context = []): void
    {
        $productStock = $this->getLockedStockRow($productId);

        $stockBefore = (int) $productStock->stock;
        $reservedBefore = (int) $productStock->reserved_stock;

        if ($reservedBefore > 0) {
            $productStock->reserved_stock = max($reservedBefore - $quantity, 0);
            $productStock->updated_at = now();
            $productStock->save();

            $this->stockMovementService->log([
                'product_id' => $productId,
                'warehouse_id' => $productStock->warehouse_id,
                'type' => 'release_reserve',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => (int) $productStock->stock,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (int) $productStock->reserved_stock,
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'note' => $context['note'] ?? 'Liberación de reserva',
                'admin_id' => $context['admin_id'] ?? null,
            ]);
        }
    }

    public function setManualStock(
        int $productId,
        int $stock,
        ?int $reservedStock = null,
        array $context = []
    ): ProductStock {
        $productStock = $this->getLockedStockRow($productId);

        $stockBefore = (int) $productStock->stock;
        $reservedBefore = (int) $productStock->reserved_stock;

        $newStock = $stock;
        $newReserved = $reservedStock ?? $reservedBefore;

        if ($newReserved > $newStock) {
            throw ValidationException::withMessages([
                'reserved_stock' => ['La reserva no puede ser mayor que el stock total.'],
            ]);
        }

        $productStock->stock = $newStock;
        $productStock->reserved_stock = $newReserved;
        $productStock->updated_at = now();
        $productStock->save();

        $this->stockMovementService->log([
            'product_id' => $productId,
            'warehouse_id' => $productStock->warehouse_id,
            'type' => 'manual_set',
            'quantity' => 0,
            'stock_before' => $stockBefore,
            'stock_after' => (int) $productStock->stock,
            'reserved_before' => $reservedBefore,
            'reserved_after' => (int) $productStock->reserved_stock,
            'reference_type' => $context['reference_type'] ?? 'admin_manual',
            'reference_id' => $context['reference_id'] ?? null,
            'note' => $context['note'] ?? 'Ajuste manual de stock',
            'admin_id' => $context['admin_id'] ?? null,
        ]);

        return $productStock;
    }
}