<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Request fields: phone (required), name, email, consent_checkbox (required true),
     * source_url, team_id
     */
    public function optInWeb(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'consent_checkbox' => 'required|accepted',
            'source_url' => 'nullable|url|max:2000',
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

        // Find or create guest by phone hash (fallback to like on phone for legacy)
        $guest = MsgGuest::query()
            ->when($teamId, fn($q) => $q->where('team_id', $teamId))
            ->where('phone_hash', $phoneHash)
            ->orWhere('phone', 'like', "%$normalized")
            ->first();

        if (!$guest) {
            $guest = new MsgGuest();
            $guest->team_id = $teamId;
        }

        if (!empty($data['name'])) {
            $guest->name = $data['name'];
        }
        if (!empty($data['email'])) {
            $guest->email = $data['email'];
        }
        // Store the phone; model mutator will maintain phone_hash
        $guest->phone = $data['phone'];
        // Do not subscribe until user confirms via YES/START reply
        $guest->is_subscribed = false;
        $guest->subscription_status_updated_at = now();
        $guest->save();

        // Record consent event for web form submission (request stage)
        MsgConsentEvent::create([
            'team_id' => $teamId,
            'msg_guest_id' => $guest->id,
            'action' => 'opt_in_request',
            'method' => 'web',
            'source' => $data['source_url'] ?? $request->headers->get('referer'),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'occurred_at' => now(),
            'meta' => [
                'consent_checkbox' => (bool) $data['consent_checkbox'],
                'consent_checked_at' => now()->toIso8601String(),
            ],
        ]);

        // Send confirmation SMS
        $business = config('messaging.help.business_name', config('app.name', 'Our Service'));
        if ($teamId) {
            $teamCfg = MsgTeamSetting::query()->where('team_id', $teamId)->first();
            if ($teamCfg && !empty($teamCfg->help_business_name)) {
                $business = $teamCfg->help_business_name;
            }
        }
        $confirmation = "Welcome to $business text notifications! Reply YES to confirm your subscription and start receiving updates. Reply STOP anytime to unsubscribe. Msg & data rates may apply. Reply HELP for help.";

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
