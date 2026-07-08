<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteGateway implements WhatsappGateway
{
    public function __construct(
        private string $token,
        private string $endpoint = 'https://api.fonnte.com/send',
    ) {}

    public function send(string $phone, string $message): bool
    {
        try {
            $res = Http::withHeaders(['Authorization' => $this->token])
                ->asForm()
                ->post($this->endpoint, ['target' => $phone, 'message' => $message]);

            return $res->successful() && ($res->json('status') === true || $res->json('status') === 'true');
        } catch (\Throwable $e) {
            Log::warning('[WA Fonnte] gagal kirim: ' . $e->getMessage());
            return false;
        }
    }
}
