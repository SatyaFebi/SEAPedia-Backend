<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'wallet_id',
        'amount',
        'type',
        'description',
    ];

    public function wallet()
    {
        return $this->belongsTo(BuyerWallet::class, 'wallet_id');
    }
}
