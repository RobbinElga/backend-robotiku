<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

class StudentStatusLog extends Model
{
    use Immutable;

    public $timestamps = false;
    protected $fillable = ['student_id', 'old_status', 'new_status', 'note', 'changed_by_type', 'changed_by'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
