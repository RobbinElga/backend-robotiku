<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolNote extends Model
{
    public $timestamps = false; // hanya created_at (default DB)
    protected $fillable = ['school_id', 'type', 'note', 'created_by', 'photo', 'kind', 'latitude', 'longitude'];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
