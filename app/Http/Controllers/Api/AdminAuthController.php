<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request)
    {
        $validated = $request->validated();

        $admin = Admin::where('email', $validated['email'])
            ->where('is_active', 1)
            ->first();

        if (!$admin || !password_verify($validated['password'], $admin->password_hash)) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        $admin->api_token = hash('sha256', Str::random(60));
        $admin->updated_at = now();
        $admin->save();

        return response()->json([
            'message' => 'Login correcto.',
            'token' => $admin->api_token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $admin = $request->attributes->get('admin');

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $admin = $request->attributes->get('admin');

        $admin->api_token = null;
        $admin->updated_at = now();
        $admin->save();

        return response()->json([
            'message' => 'Logout correcto.',
        ]);
    }
}