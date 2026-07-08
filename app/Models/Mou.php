<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mou extends Model
{
    protected $fillable = ['school_id', 'file', 'periods', 'start_date', 'end_date', 'note', 'created_by'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'periods'    => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
