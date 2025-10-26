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
    ];


    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'customer_id');
    }

    public function preferredSplitMethods()
    {
        return $this->hasMany(CustomerSplitMethod::class, 'customer_id');
    }

    public function responsibleTasks()
    {
        return $this->hasMany(ProjectTask::class, 'customer_id');
    }

    public function participatingProjects()
    {
        return $this->belongsToMany(Project::class, 'project_members', 'customer_id', 'project_id')
                    ->withPivot('role', 'del_flg')
                    ->wherePivot('del_flg', false)
                    ->withTimestamps();
    }

    public function projectMemberships()
    {
        return $this->hasMany(ProjectMember::class, 'customer_id');
    }
}

