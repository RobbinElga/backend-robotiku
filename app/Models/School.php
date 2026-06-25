<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'pic_name',
        'contact',
        'bank_account',
        'pipeline_status',
        'is_mou',
        'created_by',
    ];
    protected $casts = ['is_mou' => 'boolean'];

    public function admins()
    {
        return $this->hasMany(SchoolAdmin::class);
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }
    public function notes()
    {
        return $this->hasMany(SchoolNote::class);
    }
    public function statusLogs()
    {
        return $this->hasMany(SchoolStatusLog::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
