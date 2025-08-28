<?php

namespace Prasso\Messaging\Services;

use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgTeamSetting;
use Twilio\Rest\Client;

class SmsService
{
    /**
     * Send a plain SMS using Twilio, resolving the from number via team settings or config.
     */
    public function send(string $to, string $body, ?int $teamId = null): void
    {
        $from = null;
        if (!empty($teamId)) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            $from = $teamCfg?->sms_from ?: null;
        }
        if (empty($from)) {
            $from = config('messaging.sms_from') ?: config('twilio.phone_number');
        }
        $sid = config('twilio.sid') ?: env('TWILIO_ACCOUNT_SID');
        $token = config('twilio.auth_token') ?: env('TWILIO_AUTH_TOKEN');

        if (empty($sid) || empty($token) || empty($from)) {
            Log::error('SmsService missing configuration', [
                'sid' => (bool) $sid,
                'token' => (bool) $token,
                'from' => $from,
            ]);
            throw new \RuntimeException('SMS configuration incomplete');
        }

        $e164To = str_starts_with($to, '+') ? $to : ('+' . preg_replace('/\D+/', '', $to));

        $client = new Client($sid, $token);
        $client->messages->create($e164To, [
            'from' => $from,
            'body' => $body,
        ]);
    }
}
