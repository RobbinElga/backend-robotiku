<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSettlement extends Model
{
    protected $fillable = ['school_id', 'gross_amount', 'commission_percent', 'commission_amount', 'net_amount', 'bank_account_id', 'proof_file', 'status', 'created_by', 'verified_by', 'verified_at', 'note'];
    protected $casts = ['verified_at' => 'datetime', 'commission_percent' => 'decimal:2'];
    public function school()
    {
        return $this->belongsTo(School::class);
    }
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'school_settlement_invoices');
    }
}
