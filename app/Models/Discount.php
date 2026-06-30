<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'type',
        'amount_type',
        'value',
        'max_usage',
        'used_count',
        'expiry_date',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_usage' => 'integer',
        'used_count' => 'integer',
        'expiry_date' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
