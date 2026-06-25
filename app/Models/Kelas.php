<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'classes';
    protected $fillable = ['name', 'schedule', 'capacity', 'trainer_id'];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_students', 'class_id', 'student_id')->withPivot('joined_at');
    }
    public function billingSetting()
    {
        return $this->hasOne(BillingSetting::class, 'class_id');
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }
}
