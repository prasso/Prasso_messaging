<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgConsentEvent;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgTeamSetting;
use Prasso\Messaging\Services\SmsService;

class ConsentController extends Controller
{
    /**
     * Accept web form opt-in submission and send double opt-in confirmation SMS.
     *
     * Request fields: phone (required), name, email (required), checkbox (required true),
     * source_url, ip, ua, team_id
     */
    public function optInWeb(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            // accept either `checkbox` or legacy `consent_checkbox`
            'checkbox' => 'nullable',
            'consent_checkbox' => 'nullable',
            'source_url' => 'nullable|string|max:2000',
            'ip' => 'nullable|ip',
            'ua' => 'nullable|string|max:1000',
            'team_id' => 'nullable|integer',
        ]);

        $teamId = $data['team_id'] ?? null;

        // Normalize phone to 10-digit US/CA for hashing and matching
        $normalized = preg_replace('/[^0-9]/', '', (string) $data['phone']);
        if (strlen($normalized) === 11 && str_starts_with($normalized, '1')) {
            $normalized = substr($normalized, 1);
        }
        if (strlen($normalized) !== 10) {
            return response()->json(['message' => 'Invalid phone number format'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $phoneHash = hash('sha256', $normalized);

        // Determine checkbox acceptance from provided fields EARLY
        $checkboxAccepted = filter_var($data['checkbox'] ?? $data['consent_checkbox'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ($data['checkbox'] ?? $data['consent_checkbox'] ?? null) === 'on'
            || ($data['checkbox'] ?? $data['consent_checkbox'] ?? null) === 1
            || ($data['checkbox'] ?? $data['consent_checkbox'] ?? null) === '1'
            || ($data['checkbox'] ?? $data['consent_checkbox'] ?? null) === true;
        if (!$checkboxAccepted) {
            return response()->json(['message' => 'Consent checkbox must be accepted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find or create guest by phone hash within team scope (fallback to like on phone for legacy)
        $guestQuery = MsgGuest::query();
        if ($teamId) {
            $guestQuery->where('team_id', $teamId);
        }
        $emailHash = null;
        if (!empty($data['email'])) {
            $emailHash = hash('sha256', strtolower(trim((string) $data['email'])));
        }
        $guest = $guestQuery
            ->where(function ($q) use ($phoneHash, $normalized, $emailHash) {
                $q->where('phone_hash', $phoneHash)
                  ->orWhere('phone', 'like', "%$normalized");
                if ($emailHash) {
                    $q->orWhere('email_hash', $emailHash);
                }
            })
            ->first();

        if (!$guest) {
            $guest = new MsgGuest();
            $guest->team_id = $teamId;
        }

        // Some older schemas require non-null user_id, name, and email.
        // Provide reasonable fallbacks when absent from the form.
        // Always set name/email explicitly to avoid decrypting legacy/plaintext values
        $guest->name = !empty($data['name']) ? $data['name'] : 'SMS Guest';
        $guest->email = $data['email'];
        if (!isset($guest->user_id)) {
            // Default unattached user reference for legacy schema
            $guest->user_id = 0;
        }
        // Store the phone; model mutator will maintain phone_hash
        $guest->phone = $data['phone'];
        // Do not subscribe until user confirms via YES/START reply
        $guest->is_subscribed = false;
        $guest->subscription_status_updated_at = now();
        try {
            $guest->save();
        } catch (\Throwable $e) {
            Log::error('Failed to save guest on web opt-in', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to record guest',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Record consent event for web form submission (request stage)
        try {
            MsgConsentEvent::create([
            'msg_guest_id' => $guest->id,
            'action' => 'opt_in_request',
            'method' => 'web',
            'source' => $data['source_url'] ?? $request->headers->get('referer'),
            'ip' => $data['ip'] ?? $request->ip(),
            'user_agent' => (string) ($data['ua'] ?? $request->userAgent()),
            'occurred_at' => now(),
            'meta' => [
                'consent_checkbox' => (bool) $checkboxAccepted,
                'consent_checked_at' => now()->toIso8601String(),
            ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create consent event on web opt-in', [
                'guest_id' => $guest->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to record consent event',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Send confirmation SMS
        $business = config('messaging.help.business_name', config('app.name', 'Our Service'));
        if ($teamId) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            if ($teamCfg && !empty($teamCfg->help_business_name)) {
                $business = $teamCfg->help_business_name;
            }
        }
        // Confirmation SMS copy derived from consent form language with monthly frequency disclosure
        $cap = (int) config('messaging.rate_limit.per_guest_monthly_cap', 4);
        $confirmation = "You're almost done! Reply YES to confirm your $business text notifications (up to {$cap} messages/month). You'll receive appointment reminders, service updates, and occasional offers. Reply STOP to opt out, HELP for help. Msg & data rates may apply.";

        try {
            app(SmsService::class)->send($guest->phone, $confirmation, $teamId);
        } catch (\Throwable $e) {
            Log::error('Failed to send confirmation SMS', [
                'guest_id' => $guest->id,
                'error' => $e->getMessage(),
            ]);
            // We still return 202 Accepted so the form UX can proceed; client may retry.
            return response()->json([
                'message' => 'Opt-in recorded, awaiting confirmation reply. Confirmation SMS send failed, will need retry.',
            ], Response::HTTP_ACCEPTED);
        }

        return response()->json([
            'message' => 'Opt-in recorded. Please reply YES to confirm your subscription.',
        ], Response::HTTP_ACCEPTED);
    }
}
