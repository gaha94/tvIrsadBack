<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $adminDashboardService
    ) {}

    public function index()
    {
        return response()->json(
            $this->adminDashboardService->execute()
        );
    }
}