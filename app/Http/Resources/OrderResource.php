<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),

            'totals' => [
                'subtotal' => $this->subtotal,
                'shipping_amount' => $this->shipping_amount,
                'discount_amount' => $this->discount_amount,
                'total' => $this->total,
            ],

            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            'status_logs' => OrderStatusLogResource::collection(
                $this->whenLoaded('statusLogs')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}