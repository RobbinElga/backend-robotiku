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
        'verified_by_school_admin',
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
    public function schoolVerifier()
    {
        return $this->belongsTo(\App\Models\SchoolAdmin::class, 'verified_by_school_admin');
    }
    public function getDetailRouteAttribute(): string
    {
        return url('/api/v1/bayar/payments/' . $this->id . '/proof');
    }
    public function getVerifikasiRouteAttribute(): string
    {
        return url('/api/v1/bayar/payments/' . $this->id . '/verify');
    }
}
