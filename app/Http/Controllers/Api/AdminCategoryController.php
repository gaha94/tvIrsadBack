<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminCategoryRequest;
use App\Http\Requests\UpdateAdminCategoryRequest;
use App\Http\Requests\UpdateAdminCategoryStatusRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
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
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
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
        $hasParentId = $request->has('parent_id');
        $parentId = $request->query('parent_id');

        $query = Category::query();

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if ($hasIsActive && !is_null($isActive)) {
            $query->where('is_active', $isActive ? 1 : 0);
        }

        if ($hasParentId && !is_null($parentId) && $parentId !== '') {
            $query->where('parent_id', (int) $parentId);
        }

        $categories = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'current_page' => $categories->currentPage(),
            'data' => CategoryResource::collection($categories->getCollection()),
            'last_page' => $categories->lastPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(),
        ]);
    }

    public function show($id)
    {
        return new CategoryResource(Category::findOrFail($id));
    }

    public function store(StoreAdminCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = Category::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (new CategoryResource($category))
            ->additional(['message' => 'Categoría creada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAdminCategoryRequest $request, $id)
    {
        $validated = $request->validated();

        $category = Category::findOrFail($id);

        $category->update([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : $category->is_active,
            'updated_at' => now(),
        ]);

        return (new CategoryResource($category))
            ->additional(['message' => 'Categoría actualizada correctamente.']);
    }

    public function updateStatus(UpdateAdminCategoryStatusRequest $request, $id)
    {
        $category = Category::findOrFail($id);

        $category->is_active = $request->validated()['is_active'] ? 1 : 0;
        $category->updated_at = now();
        $category->save();

        return (new CategoryResource($category))
            ->additional(['message' => 'Estado de la categoría actualizado correctamente.']);
    }
}