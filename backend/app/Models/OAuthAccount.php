<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthAccount extends Model
{
    use HasFactory;

    protected $table = 'oauth_accounts';

    protected $fillable = [
        'customer_id',
        'provider_name',
        'provider_user_id',
        'access_token',
        'refresh_token',
        'token_expired_date',
    ];

    protected $casts = [
        'token_expired_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}

