<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationReview extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'reviewer_name',
        'rating',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
