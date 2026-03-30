<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Validation\ValidationException;

class StockService
{
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

        $availableStock = max(((int) $productStock->stock) - ((int) $productStock->reserved_stock), 0);

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

        $productStock->reserved_stock = ((int) $productStock->reserved_stock) + $quantity;
        $productStock->updated_at = now();
        $productStock->save();
    }

    public function confirmStock(int $productId, int $quantity, array $context = []): void
    {
        $productStock = $this->getLockedStockRow($productId);

        $currentStock = (int) $productStock->stock;
        $currentReserved = (int) $productStock->reserved_stock;

        if ($currentReserved < $quantity) {
            throw ValidationException::withMessages([
                'items' => [[
                    'product_id' => $productId,
                    'product_code' => $context['product_code'] ?? null,
                    'product_name' => $context['product_name'] ?? null,
                    'quantity' => $quantity,
                    'reserved_stock' => $currentReserved,
                    'message' => 'La reserva actual no alcanza para confirmar la orden.',
                ]]
            ]);
        }

        if ($currentStock < $quantity) {
            throw ValidationException::withMessages([
                'items' => [[
                    'product_id' => $productId,
                    'product_code' => $context['product_code'] ?? null,
                    'product_name' => $context['product_name'] ?? null,
                    'quantity' => $quantity,
                    'stock' => $currentStock,
                    'message' => 'El stock actual no alcanza para confirmar la orden.',
                ]]
            ]);
        }

        $productStock->stock = $currentStock - $quantity;
        $productStock->reserved_stock = max($currentReserved - $quantity, 0);
        $productStock->updated_at = now();
        $productStock->save();
    }

    public function releaseReservedStock(int $productId, int $quantity): void
    {
        $productStock = $this->getLockedStockRow($productId);

        $currentReserved = (int) $productStock->reserved_stock;

        if ($currentReserved > 0) {
            $productStock->reserved_stock = max($currentReserved - $quantity, 0);
            $productStock->updated_at = now();
            $productStock->save();
        }
    }
}