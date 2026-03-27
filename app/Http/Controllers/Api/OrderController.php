<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\OrderStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer.first_name' => ['required', 'string', 'max:120'],
            'customer.last_name' => ['nullable', 'string', 'max:120'],
            'customer.email' => ['required', 'email', 'max:190'],
            'customer.phone' => ['nullable', 'string', 'max:30'],

            'shipping.department' => ['nullable', 'string', 'max:100'],
            'shipping.province' => ['nullable', 'string', 'max:100'],
            'shipping.district' => ['nullable', 'string', 'max:100'],
            'shipping.address_line1' => ['required', 'string', 'max:255'],
            'shipping.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping.reference_text' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            'payment_method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $order = DB::transaction(function () use ($validated) {
            $customerData = $validated['customer'];
            $shippingData = $validated['shipping'];
            $itemsData = $validated['items'];

            $customer = Customer::firstOrCreate(
                ['email' => $customerData['email']],
                [
                    'first_name' => $customerData['first_name'],
                    'last_name' => $customerData['last_name'] ?? null,
                    'phone' => $customerData['phone'] ?? null,
                    'password_hash' => '',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $customer->update([
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'updated_at' => now(),
            ]);

            CustomerAddress::create([
                'customer_id' => $customer->id,
                'label' => 'Principal',
                'recipient_name' => trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? '')),
                'phone' => $customerData['phone'] ?? null,
                'department' => $shippingData['department'] ?? null,
                'province' => $shippingData['province'] ?? null,
                'district' => $shippingData['district'] ?? null,
                'address_line1' => $shippingData['address_line1'],
                'address_line2' => $shippingData['address_line2'] ?? null,
                'reference_text' => $shippingData['reference_text'] ?? null,
                'is_default' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIds = collect($itemsData)->pluck('product_id')->unique()->values();

            $products = Product::with([
                    'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at'
                ])
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $orderItems = [];

            $stockErrors = [];

            foreach ($itemsData as $item) {
                $product = $products->get($item['product_id']);

                if (!$product || !$product->is_active) {
                    $stockErrors[] = [
                        'product_id' => $item['product_id'],
                        'message' => 'Producto no válido o inactivo.',
                    ];
                    continue;
                }

                $quantity = (int) $item['quantity'];

                $stockTotal = (int) $product->stocks->sum('stock');
                $reservedStockTotal = (int) $product->stocks->sum('reserved_stock');
                $availableStock = max($stockTotal - $reservedStockTotal, 0);

                if ($availableStock < $quantity) {
                    $stockErrors[] = [
                        'product_id' => $product->id,
                        'product_code' => $product->code,
                        'product_name' => $product->name,
                        'requested_quantity' => $quantity,
                        'available_stock' => $availableStock,
                        'message' => 'Stock insuficiente.',
                    ];
                    continue;
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price ?? 0);
                $lineTotal = $unitPrice * $quantity;

                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if (!empty($stockErrors)) {
                throw ValidationException::withMessages([
                    'items' => $stockErrors,
                ]);
            }

            $shippingAmount = 0;
            $discountAmount = 0;
            $total = $subtotal + $shippingAmount - $discountAmount;

            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'customer_name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'subtotal' => $subtotal,
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_code' => $item['product_code'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($orderItems as $item) {
                $productStock = ProductStock::where('product_id', $item['product_id'])
                    ->orderBy('warehouse_id', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$productStock) {
                    throw ValidationException::withMessages([
                        'items' => [[
                            'product_id' => $item['product_id'],
                            'message' => 'No se encontró registro de stock para el producto.',
                        ]]
                    ]);
                } 

                $availableStock = max(((int) $productStock->stock) - ((int) $productStock->reserved_stock), 0);

                if ($availableStock < (int) $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => [[
                            'product_id' => $item['product_id'],
                            'product_code' => $item['product_code'],
                            'product_name' => $item['product_name'],
                            'requested_quantity' => (int) $item['quantity'],
                            'available_stock' => $availableStock,
                            'message' => 'Stock insuficiente al momento de reservar.',
                        ]]
                    ]);
                }

                $productStock->reserved_stock = ((int) $productStock->reserved_stock) + ((int) $item['quantity']);
                $productStock->updated_at = now();
                $productStock->save();
            }            

            return $order->load('items');
        });

        return response()->json([
            'message' => 'Orden creada correctamente',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
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
            ],
        ], 201);
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'IRSAD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function show($id)
    {
        $order = Order::with([
                'items',
                'customer',
                'statusLogs',
            ])
            ->findOrFail($id);

        return response()->json([
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

            'totals' => [
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
            ],

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
            'status_logs' => $order->statusLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'old_status' => $log->old_status,
                    'new_status' => $log->new_status,
                    'note' => $log->note,
                    'changed_at' => $log->changed_at,
                ];
            })->values(),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ]);
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

        return response()->json([
            'current_page' => $orders->currentPage(),
            'data' => collect($orders->items())->map(function ($order) {
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
            })->values(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ]);
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

        return response()->json([
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
            'totals' => [
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
            ],
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
            'status_logs' => $order->statusLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'old_status' => $log->old_status,
                    'new_status' => $log->new_status,
                    'note' => $log->note,
                    'changed_at' => $log->changed_at,
                ];
            })->values(),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,paid,confirmed,cancelled'],
            'payment_status' => ['nullable', 'string', 'in:pending,paid,failed,refunded'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = DB::transaction(function () use ($validated, $id) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($id);

            $oldStatus = $order->status;
            $newStatus = $validated['status'];

            if ($oldStatus === $newStatus) {
                if (array_key_exists('payment_status', $validated)) {
                    $order->payment_status = $validated['payment_status'];
                    $order->updated_at = now();
                    $order->save();
                }

                return $order->load('items');
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
                $productStock = ProductStock::where('product_id', $item->product_id)
                    ->orderBy('warehouse_id', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$productStock) {
                    throw ValidationException::withMessages([
                        'items' => [[
                            'product_id' => $item->product_id,
                            'message' => 'No se encontró registro de stock para el producto.',
                        ]],
                    ]);
                }

                $qty = (int) $item->quantity;
                $currentStock = (int) $productStock->stock;
                $currentReserved = (int) $productStock->reserved_stock;

                if (in_array($newStatus, ['paid', 'confirmed'], true)) {
                    if ($currentReserved < $qty) {
                        throw ValidationException::withMessages([
                            'items' => [[
                                'product_id' => $item->product_id,
                                'product_code' => $item->product_code,
                                'product_name' => $item->product_name,
                                'quantity' => $qty,
                                'reserved_stock' => $currentReserved,
                                'message' => 'La reserva actual no alcanza para confirmar la orden.',
                            ]],
                        ]);
                    }

                    if ($currentStock < $qty) {
                        throw ValidationException::withMessages([
                            'items' => [[
                                'product_id' => $item->product_id,
                                'product_code' => $item->product_code,
                                'product_name' => $item->product_name,
                                'quantity' => $qty,
                                'stock' => $currentStock,
                                'message' => 'El stock actual no alcanza para confirmar la orden.',
                            ]],
                        ]);
                    }

                    $productStock->stock = $currentStock - $qty;
                    $productStock->reserved_stock = max($currentReserved - $qty, 0);
                    $productStock->updated_at = now();
                    $productStock->save();
                }

                if ($newStatus === 'cancelled') {
                    if ($currentReserved > 0) {
                        $productStock->reserved_stock = max($currentReserved - $qty, 0);
                        $productStock->updated_at = now();
                        $productStock->save();
                    }
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

        return response()->json([
            'message' => 'Estado de la orden actualizado correctamente.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
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
            ],
        ]);
    }
}