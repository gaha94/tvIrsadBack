<?php

namespace App\Services\Products;

use App\Models\Brand;
use App\Models\Category;

class ProductFiltersService
{
    public function getFilters(): array
    {
        return [
            'brands' => Brand::select('id', 'name', 'slug')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(),

            'categories' => Category::select('id', 'name', 'slug')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(),
        ];
    }
}