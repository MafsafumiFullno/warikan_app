<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSplitMethod extends Model
{
    use HasFactory;

    protected $primaryKey = 'split_method_id';

    protected $fillable = [
        'description',
        'template_type',
        'customer_id',
        'del_flg',
    ];

    protected $casts = [
        'del_flg' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
