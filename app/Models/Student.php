<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_code',
        'name',
        'birth_date',
        'gender',
        'shirt_size',
        'school_origin',
        'school_grade',
        'address',
        'allergy_notes',
        'photo_permission',
        'parent_id',
        'school_id',
        'status',
        'registration_type',
        'period_quota',
        'joined_at',
        'is_verified',
        'program_id',
    ];
    protected $casts = [
        'birth_date' => 'date',
        'joined_at'        => 'date',
        'photo_permission' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(StudentParent::class, 'parent_id');
    }
    public function school()
    {
        return $this->belongsTo(School::class);
    }
    public function classes()
    {
        return $this->belongsToMany(Kelas::class, 'class_students', 'student_id', 'class_id')->withPivot('joined_at');
    }
    public function statusLogs()
    {
        return $this->hasMany(StudentStatusLog::class);
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function billingMonths()
    {
        return $this->hasMany(BillingMonth::class);
    }
    public function eReports()
    {
        return $this->hasMany(EReport::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function scopeVerified($q)
    {
        return $q->where('is_verified', true);
    }
}
