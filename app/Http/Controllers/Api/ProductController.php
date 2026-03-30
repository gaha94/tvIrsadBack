<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Services\Products\ProductFiltersService;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductFiltersService $productFiltersService
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 12);

        if ($perPage <= 0) {
            $perPage = 12;
        }

        if ($perPage > 50) {
            $perPage = 50;
        }

        $query = Product::query()
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'mainImage:id,product_id,image_path,alt_text,is_main,sort_order',
                'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
            ])
            ->where('is_active', 1);

        if ($request->filled('q')) {
            $q = trim($request->q);

            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('short_description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('featured')) {
            $featured = filter_var($request->featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (!is_null($featured)) {
                $query->where('is_featured', $featured ? 1 : 0);
            }
        }

        if ($request->filled('in_stock')) {
            $inStock = filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (!is_null($inStock)) {
                $query->withSum('stocks as stock_total_sum', 'stock')
                    ->withSum('stocks as reserved_stock_total_sum', 'reserved_stock');

                if ($inStock) {
                    $query->havingRaw(
                        'COALESCE(stock_total_sum, 0) - COALESCE(reserved_stock_total_sum, 0) > 0'
                    );
                } else {
                    $query->havingRaw(
                        'COALESCE(stock_total_sum, 0) - COALESCE(reserved_stock_total_sum, 0) <= 0'
                    );
                }
            }
        }

        $sort = $request->get('sort', 'latest');

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $products = $query->paginate($perPage);

        return (new ProductCollection($products))->additional([
            'filters' => $this->productFiltersService->getFilters(),
        ]);
    }

    public function show($id)
    {
        $product = Product::with([
            'brand:id,name,slug',
            'category:id,name,slug',
            'images:id,product_id,image_path,alt_text,is_main,sort_order',
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
        ])
            ->where('is_active', 1)
            ->findOrFail($id);

        return new ProductResource($product);
    }

    public function showBySlug($slug)
    {
        $product = Product::with([
            'brand:id,name,slug',
            'category:id,name,slug',
            'images:id,product_id,image_path,alt_text,is_main,sort_order',
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
        ])
            ->where('is_active', 1)
            ->where('slug', $slug)
            ->firstOrFail();

        return new ProductResource($product);
    }
}