<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\UpdateOrderStatusService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        protected UpdateOrderStatusService $updateOrderStatusService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,paid,confirmed,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:pending,paid,failed,refunded'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        $query = Order::query()->with(['items', 'customer']);

        if (!empty($validated['status'])) {
            $query->where('status', trim($validated['status']));
        }

        if (!empty($validated['payment_status'])) {
            $query->where('payment_status', trim($validated['payment_status']));
        }

        if (!empty($validated['order_number'])) {
            $query->where('order_number', 'like', '%' . trim($validated['order_number']) . '%');
        }

        if (!empty($validated['q'])) {
            $q = trim($validated['q']);

            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%");
            });
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $orders = $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return new OrderCollection($orders);
    }

    public function show(int $id)
    {
        $order = Order::with([
            'items',
            'customer',
            'statusLogs',
        ])->findOrFail($id);

        return new OrderResource($order);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,paid,confirmed,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:pending,paid,failed,refunded'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->updateOrderStatusService->execute($id, $validated);

        return response()->json([
            'step' => 'service_ok',
            'order_id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
        ]);
    }
}