<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class WhatsappSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'wa_provider' => 'fonnte',
            'wa_token'    => '', // isi via halaman Super Admin (P9)

            'wa_tpl_session_start' =>
            "Assalamu'alaikum {sapaan}. Sesi Robotiku ananda {nama_anak} telah dimulai. Mohon doanya semoga kegiatan belajar hari ini berjalan lancar.",
            'wa_tpl_hadir' =>
            "Terima kasih {sapaan}. Ananda {nama_anak} telah hadir mengikuti kelas Robotiku hari ini.",
            'wa_tpl_izin' =>
            "Baik {sapaan}. Semoga urusannya dilancarkan. Kami tunggu kehadiran ananda {nama_anak} pada pertemuan berikutnya.",
            'wa_tpl_sakit' =>
            "Semoga ananda {nama_anak} lekas sembuh. Semoga Allah mengangkat penyakitnya dan segera bisa belajar bersama kembali.",
            'wa_tpl_alpha' =>
            "Assalamu'alaikum {sapaan}. Hari ini ananda {nama_anak} belum hadir dan kami belum menerima informasi izin. Apakah ada kendala?",
            'wa_tpl_session_end' =>
            "Assalamu'alaikum {sapaan}. Sesi Robotiku hari ini telah selesai. Silakan menjemput ananda {nama_anak}. Apabila ada pertanyaan silakan hubungi Trainer: {nama_trainer} ({nomor_trainer}).",
            'wa_tpl_spp_reminder' =>
            "Assalamu'alaikum {sapaan}. Ananda {nama_anak} telah menyelesaikan 4 kali pertemuan Robotiku. Untuk melanjutkan pembelajaran periode berikutnya, pembayaran SPP sudah dapat dilakukan. Terima kasih atas kepercayaan {sapaan} kepada Robotiku.",
            'wa_tpl_payment_received' =>
            "Assalamu'alaikum {sapaan}. Bukti pembayaran ananda {nama_anak} telah kami terima dan sedang diverifikasi.",
            'wa_tpl_payment_confirmed' =>
            "Alhamdulillah, pembayaran ananda {nama_anak} telah dikonfirmasi (Lunas). Terima kasih {sapaan}.",
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
