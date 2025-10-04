<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'is_guest',
        'first_name',
        'last_name',
        'nick_name',
        'email',
        'password',
        'del_flg',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'del_flg' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function oauthAccounts()
    {
        return $this->hasMany(OAuthAccount::class, 'customer_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'customer_id');
    }

    public function customerSplitMethods()
    {
        return $this->hasMany(CustomerSplitMethod::class, 'customer_id');
    }

    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class, 'customer_id');
    }
}

