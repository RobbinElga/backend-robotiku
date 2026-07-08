<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    protected $fillable = ['name', 'scope', 'school_id', 'number', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'number' => 'integer'];
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
