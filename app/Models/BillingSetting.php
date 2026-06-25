<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    use HasFactory;

    protected $fillable = ['class_id', 'registration_fee', 'price_per_cycle', 'updated_by'];
    protected $casts = [
        'registration_fee' => 'decimal:2',
        'price_per_cycle' => 'decimal:2',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }
}
