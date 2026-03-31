<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Services\Stock\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrderService
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function execute(array $validated): Order
    {
        $customerData = $validated['customer'];
        $shippingData = $validated['shipping'];

        $customerData['first_name'] = trim($customerData['first_name']);
        $customerData['last_name'] = isset($customerData['last_name']) ? trim($customerData['last_name']) : null;
        $customerData['email'] = strtolower(trim($customerData['email']));
        $customerData['phone'] = trim($customerData['phone']);

        $shippingData['department'] = trim($shippingData['department']);
        $shippingData['province'] = trim($shippingData['province']);
        $shippingData['district'] = trim($shippingData['district']);
        $shippingData['address_line1'] = trim($shippingData['address_line1']);
        $shippingData['address_line2'] = isset($shippingData['address_line2']) ? trim($shippingData['address_line2']) : null;
        $shippingData['reference_text'] = isset($shippingData['reference_text']) ? trim($shippingData['reference_text']) : null;

        $itemsData = collect($validated['items'])
            ->groupBy('product_id')
            ->map(function ($group) {
                return [
                    'product_id' => (int) $group->first()['product_id'],
                    'quantity' => $group->sum(fn ($item) => (int) $item['quantity']),
                ];
            })
            ->values()
            ->all();

        return DB::transaction(function () use ($validated, $customerData, $shippingData, $itemsData) {
            $customer = $this->createOrUpdateCustomer($customerData);
            $recipientName = $this->buildRecipientName($customerData);

            $this->createCustomerAddress($customer->id, $recipientName, $customerData, $shippingData);

            $products = $this->loadProducts($itemsData);

            [$subtotal, $orderItems] = $this->buildOrderItems($itemsData, $products);

            $shippingAmount = 0;
            $discountAmount = 0;
            $total = $subtotal + $shippingAmount - $discountAmount;

            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'customer_name' => $recipientName,
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
                $this->stockService->reserveStock(
                    $item['product_id'],
                    (int) $item['quantity'],
                    [
                        'product_code' => $item['product_code'],
                        'product_name' => $item['product_name'],
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'note' => 'Reserva por creación de orden ' . $order->order_number,
                    ]
                );
            }

            OrderStatusLog::create([
                'order_id' => $order->id,
                'old_status' => null,
                'new_status' => 'pending',
                'note' => 'Orden creada',
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $order->load(['items', 'statusLogs']);
        });
    }

    protected function createOrUpdateCustomer(array $customerData): Customer
    {
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

        return $customer;
    }

    protected function createCustomerAddress(int $customerId, string $recipientName, array $customerData, array $shippingData): void
    {
        CustomerAddress::create([
            'customer_id' => $customerId,
            'label' => 'Principal',
            'recipient_name' => $recipientName,
            'phone' => $customerData['phone'] ?? null,
            'department' => $shippingData['department'],
            'province' => $shippingData['province'],
            'district' => $shippingData['district'],
            'address_line1' => $shippingData['address_line1'],
            'address_line2' => $shippingData['address_line2'] ?? null,
            'reference_text' => $shippingData['reference_text'] ?? null,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function buildRecipientName(array $customerData): string
    {
        return trim(
            collect([
                $customerData['first_name'] ?? '',
                $customerData['last_name'] ?? '',
            ])->filter()->implode(' ')
        );
    }

    protected function loadProducts(array $itemsData): Collection
    {
        $productIds = collect($itemsData)->pluck('product_id')->unique()->values();

        return Product::with([
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at'
        ])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
    }

    protected function buildOrderItems(array $itemsData, Collection $products): array
    {
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
            $availableStock = $this->stockService->getAvailableStock($product);

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

            if ($unitPrice <= 0) {
                $stockErrors[] = [
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'message' => 'El producto no tiene un precio válido.',
                ];
                continue;
            }

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

        return [$subtotal, $orderItems];
    }

    protected function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'IRSAD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}