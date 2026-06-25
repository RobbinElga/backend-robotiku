<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassStudent extends Model
{
    public $timestamps = false;
    protected $table = 'class_students';
    protected $fillable = ['class_id', 'student_id', 'joined_at'];
    protected $casts = ['joined_at' => 'datetime'];
}
