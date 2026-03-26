<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $subtotal = 0;
            $orderItems = [];

            foreach ($itemsData as $item) {
                $product = $products->get($item['product_id']);

                if (!$product || !$product->is_active) {
                    abort(422, 'Uno de los productos no es válido.');
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price ?? 0);
                $quantity = (int) $item['quantity'];
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
}