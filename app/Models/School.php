<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    public const SCHEME_V1_DIRECT = 'v1_direct';
    public const SCHEME_V2_SCHOOL = 'v2_school';
    public const SCHEME_V3_COLLECTIVE = 'v3_collective';

    protected $fillable = [
        'name',
        'address',
        'pic_name',
        'contact',
        'bank_account',
        'pipeline_status',
        'is_mou',
        'commission_percent',
        'qris_image',
        'photo',
        'registration_fee',
        'price_per_cycle',
        'created_by',
        'latitude',
        'longitude',
        'geofence_radius',
        'self_managed',
        'payment_scheme',
    ];
    protected $casts = [
        'is_mou'             => 'boolean',
        'commission_percent' => 'decimal:2',
        'registration_fee'   => 'integer',
        'price_per_cycle'    => 'integer',
        'latitude'           => 'float',
        'longitude'          => 'float',
        'geofence_radius'    => 'integer',
        'self_managed'       => 'boolean',
        'payment_scheme'     => 'string',
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

    public function admins()
    {
        return $this->hasMany(SchoolAdmin::class);
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }
    public function notes()
    {
        return $this->hasMany(SchoolNote::class);
    }
    public function statusLogs()
    {
        return $this->hasMany(SchoolStatusLog::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function mous()
    {
        return $this->hasMany(Mou::class);
    }
    public function classes()
    {
        return $this->hasMany(Kelas::class);
    }
}
