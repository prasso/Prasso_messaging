<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgGuest;

class PrivacyController extends BaseController
{
    public function markDoNotContact(Request $request, int $id)
    {
        $guest = MsgGuest::query()->findOrFail($id);
        $guest->do_not_contact = true;
        $guest->is_subscribed = false;
        $guest->subscription_status_updated_at = now();
        $guest->save();

        Log::info('Privacy: mark do-not-contact', ['msg_guest_id' => $guest->id, 'by_user_id' => optional($request->user())->id]);
        return response()->json(['status' => 'ok', 'guest_id' => $guest->id, 'do_not_contact' => true]);
    }

    public function clearDoNotContact(Request $request, int $id)
    {
        $guest = MsgGuest::query()->findOrFail($id);
        $guest->do_not_contact = false;
        $guest->save();

        Log::info('Privacy: clear do-not-contact', ['msg_guest_id' => $guest->id, 'by_user_id' => optional($request->user())->id]);
        return response()->json(['status' => 'ok', 'guest_id' => $guest->id, 'do_not_contact' => false]);
    }

    public function anonymize(Request $request, int $id)
    {
        $guest = MsgGuest::query()->findOrFail($id);

        DB::transaction(function () use ($guest, $request) {
            $guest->name = null;
            $guest->email = null;
            $guest->phone = null;
            $guest->email_hash = null;
            $guest->phone_hash = null;
            $guest->is_subscribed = false;
            $guest->do_not_contact = true;
            $guest->anonymized_at = now();
            $guest->save();
        });

        Log::info('Privacy: anonymize guest', ['msg_guest_id' => $guest->id, 'by_user_id' => optional($request->user())->id]);
        return response()->json(['status' => 'ok', 'guest_id' => $guest->id, 'anonymized_at' => $guest->anonymized_at]);
    }

    public function destroy(Request $request, int $id)
    {
        $guest = MsgGuest::query()->findOrFail($id);

        DB::transaction(function () use ($guest) {
            // Detach pivot relations if present
            if (method_exists($guest, 'messages')) {
                $guest->messages()->detach();
            }
            if (method_exists($guest, 'engagementResponses')) {
                $guest->engagementResponses()->delete();
            }
            $guest->delete();
        });

        Log::info('Privacy: delete guest', ['msg_guest_id' => $id, 'by_user_id' => optional($request->user())->id]);
        return response()->json(['status' => 'ok', 'deleted_guest_id' => $id]);
    }
}
