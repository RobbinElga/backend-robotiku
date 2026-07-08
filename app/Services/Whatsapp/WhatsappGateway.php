<?php

namespace App\Services\Whatsapp;

interface WhatsappGateway
{
    public function send(string $phone, string $message): bool;
}
