<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingContent extends Model
{
    protected $fillable = ['section', 'content', 'updated_by'];
    protected $casts = ['content' => 'array'];
}
