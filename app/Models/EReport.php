<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EReport extends Model
{
    use HasFactory;

    protected $table = 'e_reports';
    protected $fillable = [
        'student_id',
        'trainer_id',
        'class_id',
        'semester',
        'year',
        'skill_building',
        'skill_imagination',
        'skill_creativity',
        'skill_logic',
        'behavior_punctual',
        'behavior_stay',
        'behavior_communication',
        'behavior_responsibility',
        'comments',
        'topics',
        'report_place',
        'report_date',
        'signature_image',
    ];
    protected $casts = ['topics' => 'array', 'report_date' => 'date'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }
}
