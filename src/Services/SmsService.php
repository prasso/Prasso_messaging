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

        // Normalize phone number to E.164 format
        if (str_starts_with($to, '+')) {
            // Already in E.164 format
            $e164To = $to;
        } else {
            // Strip all non-digits
            $digits = preg_replace('/\D+/', '', $to);

            // Handle US/Canada numbers (10 or 11 digits starting with 1)
            if (strlen($digits) === 10) {
                // 10-digit US/Canada number: prepend +1
                $e164To = '+1' . $digits;
            } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                // 11-digit US/Canada number with leading 1: strip 1 and prepend +1
                $e164To = '+1' . substr($digits, 1);
            } else {
                // International number: just prepend +
                $e164To = '+' . $digits;
            }
        }

        $client = new Client($sid, $token);
        $client->messages->create($e164To, [
            'from' => $from,
            'body' => $body,
        ]);
    }
}
