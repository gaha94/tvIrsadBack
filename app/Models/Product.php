<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'legacy_product_id',
        'code',
        'name',
        'slug',
        'description',
        'short_description',
        'brand_id',
        'category_id',
        'price',
        'sale_price',
        'is_featured',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderBy('is_main', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }

    public function mainImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')
            ->where('is_main', 1);
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class, 'product_id');
    }
}