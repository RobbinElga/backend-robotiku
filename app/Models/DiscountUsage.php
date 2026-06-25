<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountUsage extends Model
{
    public $timestamps = false;
    protected $fillable = ['discount_code_id', 'student_id', 'invoice_id', 'used_at'];
    protected $casts = ['used_at' => 'datetime'];

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
