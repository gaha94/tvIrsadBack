<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;

class FilterController extends Controller
{
    public function index()
    {
        $brands = Brand::query()
            ->where('is_active', 1)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'slug']);

        $categories = Category::query()
            ->where('is_active', 1)
            ->orderBy('name', 'asc')
            ->get(['id', 'parent_id', 'name', 'slug']);

        return response()->json([
            'brands' => $brands,
            'categories' => $categories,
        ]);
    }
}