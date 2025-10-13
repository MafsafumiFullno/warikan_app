<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $primaryKey = 'project_id';

    protected $fillable = [
        'customer_id',
        'project_name',
        'description',
        'project_status',
        'split_method_id',
        'del_flg',
    ];

    protected $casts = [
        'del_flg' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    public function members()
    {
        return $this->belongsToMany(Customer::class, 'project_members', 'project_id', 'customer_id')
                    ->withPivot('role', 'del_flg')
                    ->wherePivot('del_flg', false)
                    ->withTimestamps();
    }

    public function projectMembers()
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }
}
