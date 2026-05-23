<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'token_hash',
        'token_encrypted',
        'permission',
        'created_by_customer_id',
        'del_flg',
    ];

    protected $casts = [
        'del_flg' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(Customer::class, 'created_by_customer_id');
    }
}
