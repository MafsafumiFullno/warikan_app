<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $fillable = [
        'project_id',
        'project_member_id',
        'customer_id',
        'member_name',
        'member_email',
        'memo',
        'role_id',
        'split_weight',
        'del_flg',
    ];

    protected $casts = [
        'del_flg' => 'boolean',
        'split_weight' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function role()
    {
        return $this->belongsTo(ProjectRole::class, 'role_id');
    }

    public function taskMembers()
    {
        return $this->hasMany(ProjectTaskMember::class, 'member_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(ProjectTask::class, 'project_task_members', 'member_id', 'task_id')
                    ->where('project_task_members.del_flg', false);
    }
}
