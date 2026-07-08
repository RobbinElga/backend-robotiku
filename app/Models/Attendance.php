<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = ['class_id', 'student_id', 'trainer_id', 'photo', 'status', 'report', 'attended_at', 'session_id', 'score'];
    protected $casts = ['attended_at' => 'datetime'];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
