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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
