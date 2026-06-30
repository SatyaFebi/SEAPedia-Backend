<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryJob extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'order_id',
        'driver_id',
        'status',
        'earned_amount',
        'taken_at',
        'completed_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'completed_at' => 'datetime',
        'earned_amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
