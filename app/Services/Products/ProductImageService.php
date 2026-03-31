<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    protected string $disk = 'public';

    public function upload(Product $product, UploadedFile $file, ?string $altText = null, bool $isMain = false): ProductImage
    {
        return DB::transaction(function () use ($product, $file, $altText, $isMain) {
            $nextSortOrder = (int) ProductImage::where('product_id', $product->id)->max('sort_order') + 1;

            $filename = $this->buildFilename($product, $file);
            $path = $file->storeAs('products', $filename, $this->disk);

            if ($isMain) {
                ProductImage::where('product_id', $product->id)->update([
                    'is_main' => 0,
                    'updated_at' => now(),
                ]);
            }

            $image = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'alt_text' => $altText,
                'sort_order' => $nextSortOrder,
                'is_main' => $isMain ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $image;
        });
    }

    public function setMain(Product $product, ProductImage $image): ProductImage
    {
        if ((int) $image->product_id !== (int) $product->id) {
            throw ValidationException::withMessages([
                'image' => ['La imagen no pertenece al producto indicado.'],
            ]);
        }

        return DB::transaction(function () use ($product, $image) {
            ProductImage::where('product_id', $product->id)->update([
                'is_main' => 0,
                'updated_at' => now(),
            ]);

            $image->is_main = 1;
            $image->updated_at = now();
            $image->save();

            return $image;
        });
    }

    public function delete(Product $product, ProductImage $image): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            throw ValidationException::withMessages([
                'image' => ['La imagen no pertenece al producto indicado.'],
            ]);
        }

        DB::transaction(function () use ($product, $image) {
            $wasMain = (bool) $image->is_main;
            $imagePath = $image->image_path;

            $image->delete();

            if ($imagePath && Storage::disk($this->disk)->exists($imagePath)) {
                Storage::disk($this->disk)->delete($imagePath);
            }

            if ($wasMain) {
                $replacement = ProductImage::where('product_id', $product->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                if ($replacement) {
                    $replacement->is_main = 1;
                    $replacement->updated_at = now();
                    $replacement->save();
                }
            }
        });
    }

    protected function buildFilename(Product $product, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $safeCode = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $product->code);

        return $safeCode . '-' . time() . '-' . uniqid() . '.' . $extension;
    }
}