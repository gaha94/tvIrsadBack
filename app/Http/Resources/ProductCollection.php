<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => ProductResource::collection($this->collection),
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'total' => $this->total(),
            'filters' => $this->additional['filters'] ?? [
                'brands' => [],
                'categories' => [],
            ],
        ];
    }
}