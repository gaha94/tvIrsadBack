<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\UpdateOrderStatusService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected CreateOrderService $createOrderService,
        protected UpdateOrderStatusService $updateOrderStatusService
    ) {}

    public function store(StoreOrderRequest $request)
    {
        $order = $this->createOrderService->execute($request->validated());

        return (new OrderResource($order))
            ->additional([
                'message' => 'Orden creada correctamente',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show($id)
    {
        $order = Order::with([
            'items',
            'customer',
            'statusLogs',
        ])->findOrFail($id);

        return new OrderResource($order);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        if ($perPage <= 0) {
            $perPage = 15;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Order::query()->with(['items', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%' . trim($request->order_number) . '%');
        }

        if ($request->filled('q')) {
            $q = trim($request->q);

            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%");
            });
        }

        $orders = $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return new OrderCollection($orders);
    }

    public function showByNumber($orderNumber)
    {
        $order = Order::with([
            'items',
            'customer',
            'statusLogs',
        ])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, $id)
    {
        $order = $this->updateOrderStatusService->execute((int) $id, $request->validated());

        return (new OrderResource($order))
            ->additional([
                'message' => 'Estado de la orden actualizado correctamente.',
            ]);
    }

    private function formatOrderDetail(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,

            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'first_name' => $order->customer->first_name,
                'last_name' => $order->customer->last_name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,

            'customer_snapshot' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],

            'totals' => [
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
            ],

            'payment_method' => $order->payment_method,
            'notes' => $order->notes,

            'items' => $order->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_code' => $item->product_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ];
            })->values(),

            'status_logs' => $order->statusLogs
                ->sortBy('id')
                ->values()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'old_status' => $log->old_status,
                        'new_status' => $log->new_status,
                        'note' => $log->note,
                        'changed_at' => $log->changed_at,
                    ];
                }),

            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }

    private function formatOrderListItem(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'subtotal' => $order->subtotal,
            'shipping_amount' => $order->shipping_amount,
            'discount_amount' => $order->discount_amount,
            'total' => $order->total,
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}