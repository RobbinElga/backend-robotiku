<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class SchoolAdmin extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['school_id', 'name', 'email', 'phone', 'password', 'is_active'];
    protected $hidden = ['password'];
    protected $casts = ['password' => 'hashed', 'is_active' => 'boolean'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
