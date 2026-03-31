<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use App\Models\Order;
use App\Services\Orders\CreateOrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected CreateOrderService $createOrderService,
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
}