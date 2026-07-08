<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'signature_image'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'password'  => 'hashed',
        'is_active' => 'boolean',
    ];

    public function classes()
    {
        return $this->hasMany(Kelas::class, 'trainer_id');
    }
    public function schoolsCreated()
    {
        return $this->hasMany(School::class, 'created_by');
    }
}
