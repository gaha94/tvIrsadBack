<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderNoteRequest;
use App\Http\Resources\OrderNoteResource;
use App\Models\Order;
use App\Models\OrderNote;

class AdminOrderNoteController extends Controller
{
    public function index($orderId)
    {
        $order = Order::findOrFail($orderId);

        $notes = OrderNote::with('admin:id,name,email')
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'order_id' => $order->id,
            'notes' => OrderNoteResource::collection($notes),
        ]);
    }

    public function store(StoreOrderNoteRequest $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        // Este viene de tu middleware admin.auth
        $admin = request()->attributes->get('admin');

        $note = OrderNote::create([
            'order_id' => $order->id,
            'admin_id' => $admin?->id,
            'note' => $request->validated()['note'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (new OrderNoteResource($note->load('admin')))
            ->additional([
                'message' => 'Nota agregada correctamente.',
            ])
            ->response()
            ->setStatusCode(201);
    }
}