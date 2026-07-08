<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\WhatsappService;

class SettingController extends Controller
{
    use ApiResponse;

    public function officeLocation(): JsonResponse
    {
        return $this->success([
            'latitude'  => Setting::get('office_latitude'),
            'longitude' => Setting::get('office_longitude'),
            'radius'    => (int) Setting::get('office_radius', 500),
        ], 'Lokasi kantor.');
    }

    public function updateOfficeLocation(Request $request): JsonResponse
    {
        $d = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius'    => ['required', 'integer', 'min:50', 'max:5000'],
        ]);
        Setting::put('office_latitude', $d['latitude'], $request->user()->id);
        Setting::put('office_longitude', $d['longitude'], $request->user()->id);
        Setting::put('office_radius', $d['radius'], $request->user()->id);
        return $this->success(null, 'Lokasi kantor disimpan.');
    }

    private array $waKeys = [
        'wa_provider',
        'wa_token',
        'wa_tpl_session_start',
        'wa_tpl_hadir',
        'wa_tpl_izin',
        'wa_tpl_sakit',
        'wa_tpl_alpha',
        'wa_tpl_session_end',
        'wa_tpl_spp_reminder',
        'wa_tpl_payment_received',
        'wa_tpl_payment_confirmed',
    ];

    public function wa(): JsonResponse
    {
        $out = [];
        foreach ($this->waKeys as $k) $out[$k] = Setting::get($k, '');
        return $this->success($out, 'Pengaturan WhatsApp.');
    }

    public function updateWa(Request $request): JsonResponse
    {
        $rules = collect($this->waKeys)->mapWithKeys(fn($k) => [$k => ['nullable', 'string']])->all();
        $data = $request->validate($rules);
        foreach ($data as $k => $v) Setting::put($k, $v ?? '', $request->user()->id);
        return $this->success(null, 'Pengaturan WhatsApp disimpan.');
    }

    public function testWa(Request $request, WhatsappService $wa): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string']]);
        $ok = $wa->send($request->phone, "Tes notifikasi Robotiku ✅ — jika Anda menerima pesan ini, konfigurasi WhatsApp sudah benar.");
        return $this->success(['sent' => $ok], $ok ? 'Pesan tes terkirim.' : 'Gagal / token belum benar (cek storage/logs).');
    }

    /** Publik: gambar panduan ukuran kaos untuk form daftar. */
    public function shirtChart(): JsonResponse
    {
        $path = Setting::where('key', 'shirt_size_chart')->value('value');
        return $this->success([
            'path' => $path,
            'url'  => $path ? '/api/v1/public-media/' . $path : null,
        ], 'Panduan ukuran kaos.');
    }

    /** Admin & Super Admin: unggah/ubah gambar panduan ukuran kaos. */
    public function updateShirtChart(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120']]);

        $path = $request->file('image')->store('settings', 'local'); // simpan apa adanya (JPG/PNG), tak dikonversi

        Setting::updateOrCreate(
            ['key' => 'shirt_size_chart'],
            ['value' => $path, 'updated_by' => $request->user()->id]
        );

        return $this->success(['path' => $path, 'url' => '/api/v1/public-media/' . $path], 'Panduan ukuran kaos diperbarui.');
    }
}
