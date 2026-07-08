<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Log;

class LogGateway implements WhatsappGateway
{
    public function send(string $phone, string $message): bool
    {
        Log::info("[WA→{$phone}] {$message}");
        return true;
    }
}
