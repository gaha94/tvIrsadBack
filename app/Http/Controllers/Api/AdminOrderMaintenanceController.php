<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Orders\ExpirePendingOrdersService;
use Illuminate\Http\Request;

class AdminOrderMaintenanceController extends Controller
{
    public function expirePending(Request $request, ExpirePendingOrdersService $service)
    {
        $validated = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ]);

        $minutes = isset($validated['minutes']) ? (int) $validated['minutes'] : 30;

        $result = $service->execute($minutes);

        return response()->json([
            'message' => 'Proceso ejecutado correctamente.',
            'expired_count' => $result['expired_count'],
            'expired_order_ids' => $result['expired_order_ids'],
            'cutoff' => $result['cutoff'],
        ]);
    }
}