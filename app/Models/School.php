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
        'commission_percent',
        'qris_image',
        'photo',
        'registration_fee',
        'price_per_cycle',
        'created_by',
        'latitude',
        'longitude',
        'geofence_radius',
        'self_managed'
    ];
    protected $casts = [
        'is_mou'             => 'boolean',
        'commission_percent' => 'decimal:2',
        'registration_fee'   => 'integer',
        'price_per_cycle'    => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'geofence_radius' => 'integer',
        'self_managed' => 'boolean'
    ];

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
    public function mous()
    {
        return $this->hasMany(Mou::class);
    }
    public function classes()
    {
        return $this->hasMany(Kelas::class);
    }
}
