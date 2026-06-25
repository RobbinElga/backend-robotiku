<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

class PaymentStatusLog extends Model
{
    use Immutable;

    public $timestamps = false;
    protected $fillable = ['payment_id', 'old_status', 'new_status', 'notes', 'changed_by'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
