<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrdersService
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function execute(int $minutes = 30): array
    {
        $expiredCount = 0;
        $expiredOrderIds = [];

        $cutoff = now()->subMinutes($minutes);

        DB::transaction(function () use ($cutoff, &$expiredCount, &$expiredOrderIds) {
            $orders = Order::with(['items'])
                ->where('status', 'pending')
                ->where('created_at', '<=', $cutoff)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $this->stockService->releaseReservedStock(
                        (int) $item->product_id,
                        (int) $item->quantity,
                        [
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'note' => 'Liberación por expiración automática de orden ' . $order->order_number,
                        ]
                    );
                }

                $oldStatus = $order->status;

                $order->status = 'cancelled';
                $order->payment_status = 'failed';
                $order->updated_at = now();
                $order->save();

                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'cancelled',
                    'note' => 'Orden expirada automáticamente por tiempo límite',
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $expiredCount++;
                $expiredOrderIds[] = $order->id;
            }
        });

        return [
            'expired_count' => $expiredCount,
            'expired_order_ids' => $expiredOrderIds,
            'cutoff' => $cutoff->toDateTimeString(),
        ];
    }
}