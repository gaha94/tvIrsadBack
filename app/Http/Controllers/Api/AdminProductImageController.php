<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Products\ProductImageService;
use Illuminate\Http\Request;

class AdminProductImageController extends Controller
{
    public function __construct(
        protected ProductImageService $productImageService
    ) {}

    public function index($id)
    {
        $product = Product::findOrFail($id);

        $images = ProductImage::where('product_id', $product->id)
            ->orderByDesc('is_main')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'images' => ProductImageResource::collection($images),
        ]);
    }

    public function store(StoreProductImageRequest $request, $id)
    {
        $product = Product::findOrFail($id);

        $image = $this->productImageService->upload(
            $product,
            $request->file('image'),
            $request->validated()['alt_text'] ?? null,
            !empty($request->validated()['is_main'])
        );

        return (new ProductImageResource($image))
            ->additional([
                'message' => 'Imagen subida correctamente.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function setMain(Request $request, $id, $imageId)
    {
        $product = Product::findOrFail($id);
        $image = ProductImage::findOrFail($imageId);

        $image = $this->productImageService->setMain($product, $image);

        return (new ProductImageResource($image))
            ->additional([
                'message' => 'Imagen principal actualizada correctamente.',
            ]);
    }

    public function destroy($id, $imageId)
    {
        $product = Product::findOrFail($id);
        $image = ProductImage::findOrFail($imageId);

        $this->productImageService->delete($product, $image);

        return response()->json([
            'message' => 'Imagen eliminada correctamente.',
        ]);
    }
}