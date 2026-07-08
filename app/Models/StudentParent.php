<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentParent extends Model
{
    use HasFactory;

    protected $table = 'parents';
    protected $fillable = ['name', 'phone', 'greeting', 'phone_alt'];

    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }
}
