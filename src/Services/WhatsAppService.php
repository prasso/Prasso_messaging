<?php

namespace Prasso\Messaging\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgTeamSetting;

class WhatsAppService
{
    public function send(string $to, string $body, ?int $teamId = null): Response
    {
        [$token, $phoneNumberId, $apiVersion, $baseUrl] = $this->resolveConfig($teamId);

        if (empty($token) || empty($phoneNumberId)) {
            Log::error('WhatsAppService missing configuration', [
                'token' => (bool) $token,
                'phone_number_id' => (bool) $phoneNumberId,
                'team_id' => $teamId,
            ]);
            throw new \RuntimeException('WhatsApp configuration incomplete');
        }

        $toDigits = $this->normalizeToDigits($to);
        if ($toDigits === '') {
            throw new \InvalidArgumentException('Invalid WhatsApp recipient phone number');
        }

        $url = rtrim($baseUrl, '/') . '/' . trim($apiVersion, '/') . '/' . $phoneNumberId . '/messages';

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $toDigits,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]);
    }

    protected function resolveConfig(?int $teamId): array
    {
        $token = null;
        $phoneNumberId = null;

        if (!empty($teamId)) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            if ($teamCfg) {
                $token = $teamCfg->whatsapp_access_token ?: null;
                $phoneNumberId = $teamCfg->whatsapp_phone_number_id ?: null;
            }
        }

        if (empty($token)) {
            $token = config('messaging.whatsapp.access_token') ?: env('WHATSAPP_ACCESS_TOKEN');
        }
        if (empty($phoneNumberId)) {
            $phoneNumberId = config('messaging.whatsapp.phone_number_id') ?: env('WHATSAPP_PHONE_NUMBER_ID');
        }

        $apiVersion = config('messaging.whatsapp.api_version') ?: env('WHATSAPP_API_VERSION', 'v18.0');
        $baseUrl = config('messaging.whatsapp.base_url') ?: env('WHATSAPP_BASE_URL', 'https://graph.facebook.com');

        return [$token, $phoneNumberId, $apiVersion, $baseUrl];
    }

    protected function normalizeToDigits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
