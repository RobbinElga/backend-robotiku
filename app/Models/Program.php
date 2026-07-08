<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['name', 'level', 'registration_fee', 'price_per_cycle', 'is_active', 'is_visible'];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_visible' => 'boolean',
        'registration_fee' => 'integer',
        'price_per_cycle'  => 'integer',
    ];

    public function students()
    {
        return $this->hasMany(Student::class);
    }
    public function classes()
    {
        return $this->hasMany(Kelas::class);
    }
}
