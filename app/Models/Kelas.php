<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'classes';
    protected $fillable = [
        'name',
        'schedule',
        'capacity',
        'program_id',
        'school_id',
        'trainer_id',
    ];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_students', 'class_id', 'student_id')->withPivot('joined_at');
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'class_id');
    }

    public function trainers()
    {
        return $this->belongsToMany(User::class, 'class_trainers', 'class_id', 'trainer_id')
            ->withPivot('role')->withTimestamps();
    }
}
