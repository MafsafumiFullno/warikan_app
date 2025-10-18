<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    use HasFactory;

    protected $primaryKey = 'task_id';

    protected $fillable = [
        'project_id',
        'project_task_code',
        'task_name',
        'task_member_name',
        'customer_id',
        'member_id',
        'accounting_amount',
        'accounting_type',
        'breakdown',
        'payment_id',
        'memo',
        'del_flg',
    ];

    protected $casts = [
        'accounting_amount' => 'decimal:2',
        'del_flg' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projectMember()
    {
        return $this->belongsTo(ProjectMember::class, 'member_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function taskMembers()
    {
        return $this->hasMany(ProjectTaskMember::class, 'task_id');
    }

    public function members()
    {
        return $this->belongsToMany(ProjectMember::class, 'project_task_members', 'task_id', 'member_id')
                    ->where('project_task_members.del_flg', false);
    }
}
