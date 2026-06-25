<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;
    protected $fillable = ['recipient_type', 'recipient_id', 'title', 'message', 'type', 'is_read'];
    protected $casts = ['is_read' => 'boolean'];
}
