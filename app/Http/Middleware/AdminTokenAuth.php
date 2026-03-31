<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'message' => 'No autorizado.',
            ], 401);
        }

        $token = trim(substr($header, 7));

        if ($token === '') {
            return response()->json([
                'message' => 'Token inválido.',
            ], 401);
        }

        $admin = Admin::where('api_token', $token)
            ->where('is_active', 1)
            ->first();

        if (!$admin) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 401);
        }

        $request->attributes->set('admin', $admin);

        return $next($request);
    }
}