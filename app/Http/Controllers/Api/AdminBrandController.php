<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminBrandRequest;
use App\Http\Requests\UpdateAdminBrandRequest;
use App\Http\Requests\UpdateAdminBrandStatusRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;

class AdminBrandController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('is_active')) {
            $request->merge([
                'is_active' => filter_var(
                    $request->query('is_active'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $q = trim((string) $request->query('q', ''));
        $hasIsActive = $request->has('is_active');
        $isActive = $request->query('is_active');

        $query = Brand::query();

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if ($hasIsActive && !is_null($isActive)) {
            $query->where('is_active', $isActive ? 1 : 0);
        }

        $brands = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'current_page' => $brands->currentPage(),
            'data' => BrandResource::collection($brands->getCollection()),
            'last_page' => $brands->lastPage(),
            'per_page' => $brands->perPage(),
            'total' => $brands->total(),
        ]);
    }

    public function show($id)
    {
        return new BrandResource(Brand::findOrFail($id));
    }

    public function store(StoreAdminBrandRequest $request)
    {
        $validated = $request->validated();

        $brand = Brand::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (new BrandResource($brand))
            ->additional(['message' => 'Marca creada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAdminBrandRequest $request, $id)
    {
        $validated = $request->validated();

        $brand = Brand::findOrFail($id);

        $brand->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : $brand->is_active,
            'updated_at' => now(),
        ]);

        return (new BrandResource($brand))
            ->additional(['message' => 'Marca actualizada correctamente.']);
    }

    public function updateStatus(UpdateAdminBrandStatusRequest $request, $id)
    {
        $brand = Brand::findOrFail($id);

        $brand->is_active = $request->validated()['is_active'] ? 1 : 0;
        $brand->updated_at = now();
        $brand->save();

        return (new BrandResource($brand))
            ->additional(['message' => 'Estado de la marca actualizado correctamente.']);
    }
}