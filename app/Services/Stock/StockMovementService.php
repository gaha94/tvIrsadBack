<?php

namespace App\Services\Stock;

use App\Models\StockMovement;

class StockMovementService
{
    public function log(array $data): StockMovement
    {
        return StockMovement::create([
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'type' => $data['type'],
            'quantity' => $data['quantity'] ?? 0,
            'stock_before' => $data['stock_before'] ?? 0,
            'stock_after' => $data['stock_after'] ?? 0,
            'reserved_before' => $data['reserved_before'] ?? 0,
            'reserved_after' => $data['reserved_after'] ?? 0,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'note' => $data['note'] ?? null,
            'admin_id' => $data['admin_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}