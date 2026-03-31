<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminProductRequest;
use App\Http\Requests\UpdateAdminProductRequest;
use App\Http\Requests\UpdateAdminProductStatusRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Products\ProductFiltersService;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function __construct(
        protected ProductFiltersService $productFiltersService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        $query = Product::query()->with([
            'brand:id,name,slug',
            'category:id,name,slug',
            'mainImage:id,product_id,image_path,alt_text,is_main,sort_order',
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
        ]);

        if (!empty($validated['q'])) {
            $q = trim($validated['q']);

            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if (!empty($validated['brand_id'])) {
            $query->where('brand_id', $validated['brand_id']);
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $validated['is_active'] ? 1 : 0);
        }

        $products = $query->orderBy('id', 'desc')->paginate($perPage);

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
        ])->findOrFail($id);

        return new ProductResource($product);
    }

    public function store(StoreAdminProductRequest $request)
    {
        $validated = $request->validated();

        $product = Product::create([
            'legacy_product_id' => $validated['legacy_product_id'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'price' => $validated['price'],
            'sale_price' => $validated['sale_price'] ?? null,
            'is_featured' => !empty($validated['is_featured']) ? 1 : 0,
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product->load([
            'brand:id,name,slug',
            'category:id,name,slug',
            'images:id,product_id,image_path,alt_text,is_main,sort_order',
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
        ]);

        return (new ProductResource($product))
            ->additional(['message' => 'Producto creado correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAdminProductRequest $request, $id)
    {
        $validated = $request->validated();

        $product = Product::findOrFail($id);

        $product->update([
            'legacy_product_id' => $validated['legacy_product_id'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'price' => $validated['price'],
            'sale_price' => $validated['sale_price'] ?? null,
            'is_featured' => !empty($validated['is_featured']) ? 1 : 0,
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : $product->is_active,
            'updated_at' => now(),
        ]);

        $product->load([
            'brand:id,name,slug',
            'category:id,name,slug',
            'images:id,product_id,image_path,alt_text,is_main,sort_order',
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
        ]);

        return (new ProductResource($product))
            ->additional(['message' => 'Producto actualizado correctamente.']);
    }

    public function updateStatus(UpdateAdminProductStatusRequest $request, $id)
    {
        $product = Product::findOrFail($id);

        $product->is_active = $request->validated()['is_active'] ? 1 : 0;
        $product->updated_at = now();
        $product->save();

        $product->load([
            'brand:id,name,slug',
            'category:id,name,slug',
            'images:id,product_id,image_path,alt_text,is_main,sort_order',
            'stocks:product_id,warehouse_id,stock,reserved_stock,updated_at',
        ]);

        return (new ProductResource($product))
            ->additional(['message' => 'Estado del producto actualizado correctamente.']);
    }
}