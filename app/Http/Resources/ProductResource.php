<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stockTotal = $this->relationLoaded('stocks')
            ? (int) $this->stocks->sum('stock')
            : 0;

        $reservedStockTotal = $this->relationLoaded('stocks')
            ? (int) $this->stocks->sum('reserved_stock')
            : 0;

        $availableStock = max($stockTotal - $reservedStockTotal, 0);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,

            'stock_total' => $stockTotal,
            'reserved_stock_total' => $reservedStockTotal,
            'available_stock' => $availableStock,
            'in_stock' => $availableStock > 0,

            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ] : null,

            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,

            'main_image' => $this->whenLoaded('mainImage', function () {
                return $this->mainImage
                    ? new ProductImageResource($this->mainImage)
                    : null;
            }),

            'images' => ProductImageResource::collection(
                $this->whenLoaded('images')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}