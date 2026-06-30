<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'order_id',
        'status',
        'changed_by_role',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
