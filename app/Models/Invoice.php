<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'student_id',
        'billing_month_id',
        'base_amount',
        'registration_fee',
        'discount_amount',
        'total_amount',
        'due_date',
        'status',
    ];
    protected $casts = [
        'due_date' => 'date',
        'base_amount' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function billingMonth()
    {
        return $this->belongsTo(BillingMonth::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
