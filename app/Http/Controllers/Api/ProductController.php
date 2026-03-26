<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
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

        $products = $query->paginate($perPage)->through(function ($product) {
            $stockTotal = (int) $product->stocks->sum('stock');
            $reservedStockTotal = (int) $product->stocks->sum('reserved_stock');
            $availableStock = max($stockTotal - $reservedStockTotal, 0);

            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'is_featured' => (bool) $product->is_featured,
                'is_active' => (bool) $product->is_active,
                'stock_total' => $stockTotal,
                'reserved_stock_total' => $reservedStockTotal,
                'available_stock' => $availableStock,
                'in_stock' => $availableStock > 0,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => $product->brand->slug,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'main_image' => $product->mainImage ? [
                    'id' => $product->mainImage->id,
                    'image_path' => $product->mainImage->image_path,
                    'image_url' => $product->mainImage->image_url,
                    'alt_text' => $product->mainImage->alt_text,
                    'is_main' => (bool) $product->mainImage->is_main,
                    'sort_order' => $product->mainImage->sort_order,
                ] : null,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        });

        $filters = [
            'brands' => \App\Models\Brand::select('id', 'name', 'slug')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(),

            'categories' => \App\Models\Category::select('id', 'name', 'slug')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(),
        ];

        return response()->json([
            'current_page' => $products->currentPage(),
            'data' => $products->items(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'filters' => $filters,
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

        $stockTotal = (int) $product->stocks->sum('stock');
        $reservedStockTotal = (int) $product->stocks->sum('reserved_stock');
        $availableStock = max($stockTotal - $reservedStockTotal, 0);

        return response()->json([
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'is_featured' => (bool) $product->is_featured,
            'is_active' => (bool) $product->is_active,
            'stock_total' => $stockTotal,
            'reserved_stock_total' => $reservedStockTotal,
            'available_stock' => $availableStock,
            'in_stock' => $availableStock > 0,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'images' => $product->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => $image->image_url,
                    'alt_text' => $image->alt_text,
                    'is_main' => (bool) $image->is_main,
                    'sort_order' => $image->sort_order,
                ];
            })->values(),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ]);
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

        $stockTotal = (int) $product->stocks->sum('stock');
        $reservedStockTotal = (int) $product->stocks->sum('reserved_stock');
        $availableStock = max($stockTotal - $reservedStockTotal, 0);

        return response()->json([
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'is_featured' => (bool) $product->is_featured,
            'is_active' => (bool) $product->is_active,
            'stock_total' => $stockTotal,
            'reserved_stock_total' => $reservedStockTotal,
            'available_stock' => $availableStock,
            'in_stock' => $availableStock > 0,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'images' => $product->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => $image->image_url,
                    'alt_text' => $image->alt_text,
                    'is_main' => (bool) $image->is_main,
                    'sort_order' => $image->sort_order,
                ];
            })->values(),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ]);
    }
}