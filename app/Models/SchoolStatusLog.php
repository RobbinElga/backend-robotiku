<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

class SchoolStatusLog extends Model
{
    use Immutable;

    public $timestamps = false;
    protected $fillable = ['school_id', 'old_status', 'new_status', 'note', 'changed_by'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
