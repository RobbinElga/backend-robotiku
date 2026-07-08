<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Whatsapp\WhatsappGateway;

class WhatsappService
{
    public function __construct(private WhatsappGateway $gateway) {}

    /** Kirim pesan langsung. */
    public function send(?string $phone, string $message): bool
    {
        $phone = $this->normalize($phone);
        if (! $phone || trim($message) === '') return false;
        return $this->gateway->send($phone, $message);
    }

    /** Kirim dari template di settings dengan variabel {key}. */
    public function sendTemplate(string $settingKey, ?string $phone, array $vars = []): bool
    {
        $tpl = Setting::get($settingKey);
        if (! $tpl) return false;
        return $this->send($phone, $this->render($tpl, $vars));
    }

    public function render(string $tpl, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{' . $k . '}', (string) $v, $tpl);
        }
        return $tpl;
    }

    /** Normalisasi ke format 62xxxxxxxxxx. */
    private function normalize(?string $phone): ?string
    {
        if (! $phone) return null;
        $p = preg_replace('/\D/', '', $phone);
        if (! $p) return null;
        if (str_starts_with($p, '0'))  $p = '62' . substr($p, 1);
        if (str_starts_with($p, '620')) $p = '62' . substr($p, 3);
        return $p;
    }
}
