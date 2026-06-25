<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingMonth extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'cycle_number', 'period_month', 'period_year', 'status'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
