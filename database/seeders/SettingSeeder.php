<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'geofencing_radius_meters' => '500',
            'wa_message_template'      => "Halo {nama_ortu}, ini pengingat tagihan Robotiku untuk ananda {nama_anak} "
                . "sebesar {total} dengan nomor invoice {invoice}. Mohon segera diselesaikan. Terima kasih.",
            'office_latitude'  => '-0.026330',
            'office_longitude' => '109.342503',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
