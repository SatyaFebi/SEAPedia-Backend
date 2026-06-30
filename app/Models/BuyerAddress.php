<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyerAddress extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'address_details',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
