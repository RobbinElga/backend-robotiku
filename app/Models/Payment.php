<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'proof_file',
        'uploader_type',
        'uploader_id',
        'verified_by',
        'verified_at',
        'status',
        'notes',
    ];
    protected $casts = ['verified_at' => 'datetime'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
    public function statusLogs()
    {
        return $this->hasMany(PaymentStatusLog::class);
    }
}
