<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'quota',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
    ];
    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
    ];

    public function usages()
    {
        return $this->hasMany(DiscountUsage::class);
    }
}
