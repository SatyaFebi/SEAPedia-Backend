<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'store_id',
        'name',
        'description',
        'price',
        'stock',
        'image',
        'category',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
