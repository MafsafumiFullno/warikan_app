<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTaskMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'task_id',
        'del_flg',
    ];

    protected $casts = [
        'del_flg' => 'boolean',
    ];

    public function projectMember()
    {
        return $this->belongsTo(ProjectMember::class, 'member_id');
    }

    public function projectTask()
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }
}
