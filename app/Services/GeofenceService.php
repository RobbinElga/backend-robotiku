<?php

namespace App\Services;

use App\Models\Setting;

class GeofenceService
{
    /** @return array{within: bool, distance: float, radius: float} */
    public function check(float $lat, float $lng): array
    {
        $centerLat = (float) (Setting::where('key', 'office_latitude')->value('value') ?? 0);
        $centerLng = (float) (Setting::where('key', 'office_longitude')->value('value') ?? 0);
        $radius    = (float) (Setting::where('key', 'geofencing_radius_meters')->value('value') ?? 500);

        $distance = $this->haversine($lat, $lng, $centerLat, $centerLng);

        return [
            'within'   => $distance <= $radius,
            'distance' => round($distance, 1),
            'radius'   => $radius,
        ];
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6_371_000; // radius bumi (meter)
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
