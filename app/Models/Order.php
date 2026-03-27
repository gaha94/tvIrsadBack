<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'customer_id',
        'order_number',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'shipping_amount',
        'discount_amount',
        'total',
        'payment_method',
        'payment_status',
        'notes',
        'created_at',
        'updated_at',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class, 'order_id')->orderBy('id', 'desc');
    }
}