<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusLog extends Model
{
    protected $table = 'order_status_logs';

    protected $fillable = [
        'order_id',
        'old_status',
        'new_status',
        'note',
        'changed_at',
        'created_at',
        'updated_at',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}