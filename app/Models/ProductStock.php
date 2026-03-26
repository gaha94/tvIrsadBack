<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $table = 'product_stocks';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'stock',
        'reserved_stock',
        'updated_at',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}