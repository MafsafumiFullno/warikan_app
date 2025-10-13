<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectRole extends Model
{
    use HasFactory;

    protected $primaryKey = 'role_id';

    protected $fillable = [
        'role_code',
        'role_name',
        'description',
        'del_flg',
    ];

    protected $casts = [
        'del_flg' => 'boolean',
    ];

    public function projectMembers()
    {
        return $this->hasMany(ProjectMember::class, 'role_id');
    }
}
