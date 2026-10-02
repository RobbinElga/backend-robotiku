<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Mou extends Model
{
    public const SCHEME_V1_DIRECT = 'v1_direct';
    public const SCHEME_V2_SCHOOL = 'v2_school';
    public const SCHEME_V3_COLLECTIVE = 'v3_collective';

    protected $fillable = [
        'school_id',
        'file',
        'periods',
        'self_managed',
        'payment_scheme',
        'start_date',
        'end_date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'periods'        => 'integer',
        'self_managed'   => 'boolean',
        'payment_scheme' => 'string',
    ];

    protected function selfManaged(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => ($attributes['payment_scheme'] ?? null) === self::SCHEME_V3_COLLECTIVE || (!empty($attributes['self_managed']) && empty($attributes['payment_scheme'])),
            set: function ($value) {
                $bool = (bool) $value;
                return [
                    'self_managed'   => $bool,
                    'payment_scheme' => $bool ? self::SCHEME_V3_COLLECTIVE : self::SCHEME_V1_DIRECT,
                ];
            }
        );
    }

    protected function paymentScheme(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $value ?? (!empty($attributes['self_managed']) ? self::SCHEME_V3_COLLECTIVE : self::SCHEME_V1_DIRECT),
            set: function ($value) {
                $scheme = $value ?? self::SCHEME_V1_DIRECT;
                return [
                    'payment_scheme' => $scheme,
                    'self_managed'   => $scheme === self::SCHEME_V3_COLLECTIVE,
                ];
            }
        );
    }

    public function isV1(): bool
    {
        return $this->payment_scheme === self::SCHEME_V1_DIRECT;
    }

    public function isV2(): bool
    {
        return $this->payment_scheme === self::SCHEME_V2_SCHOOL;
    }

    public function isV3(): bool
    {
        return $this->payment_scheme === self::SCHEME_V3_COLLECTIVE;
    }

    public function requiresParentPayment(): bool
    {
        return ! $this->isV3();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
