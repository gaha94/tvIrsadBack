<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrderStatusService
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function execute(int $orderId, array $validated): Order
    {
        return DB::transaction(function () use ($orderId, $validated) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($orderId);

            $oldStatus = $order->status;
            $newStatus = $validated['status'];

            if ($oldStatus === $newStatus) {
                if (array_key_exists('payment_status', $validated)) {
                    $order->payment_status = $validated['payment_status'];
                    $order->updated_at = now();
                    $order->save();
                }

                return $order->load(['items', 'statusLogs']);
            }

            $finalStatuses = ['paid', 'confirmed', 'cancelled'];

            if (in_array($oldStatus, $finalStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "No se puede cambiar una orden que ya está en estado final ({$oldStatus})."
                    ],
                ]);
            }

            foreach ($order->items as $item) {
                if (in_array($newStatus, ['paid', 'confirmed'], true)) {
                    $this->stockService->confirmStock(
                        $item->product_id,
                        (int) $item->quantity,
                        [
                            'product_code' => $item->product_code,
                            'product_name' => $item->product_name,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'note' => 'Descuento por confirmación de orden ' . $order->order_number,
                        ]
                    );
                }

                if ($newStatus === 'cancelled') {
                    $this->stockService->releaseReservedStock(
                        $item->product_id,
                        (int) $item->quantity,
                        [
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'note' => 'Liberación por cancelación de orden ' . $order->order_number,
                        ]
                    );
                }
            }

            $order->status = $newStatus;

            if (array_key_exists('payment_status', $validated)) {
                $order->payment_status = $validated['payment_status'];
            } elseif (in_array($newStatus, ['paid', 'confirmed'], true)) {
                $order->payment_status = 'paid';
            } elseif ($newStatus === 'cancelled') {
                $order->payment_status = 'failed';
            }

            $order->updated_at = now();
            $order->save();

            OrderStatusLog::create([
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note' => $validated['note'] ?? null,
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $order->load(['items', 'statusLogs']);
        });
    }
}