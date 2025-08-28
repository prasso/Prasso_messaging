<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Prasso\Messaging\Models\MsgTeamSetting;
use Prasso\Messaging\Models\MsgTeamVerificationAudit;

class TeamVerificationController extends Controller
{
    public function getStatus(int $teamId)
    {
        $setting = MsgTeamSetting::query()->where('team_id', $teamId)->first();
        if (!$setting) {
            return response()->json(['message' => 'Team settings not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'team_id' => $teamId,
            'verification_status' => $setting->verification_status,
            'verified_at' => $setting->verified_at,
            'verification_notes' => $setting->verification_notes,
        ]);
    }

    public function listAudits(int $teamId)
    {
        $audits = MsgTeamVerificationAudit::query()
            ->where('team_id', $teamId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($audits);
    }

    public function setStatus(Request $request, int $teamId)
    {
        $data = $request->validate([
            'status' => 'required|string|in:verified,pending,rejected,suspended',
            'notes' => 'nullable|string',
        ]);

        $setting = MsgTeamSetting::query()->where('team_id', $teamId)->first();
        if (!$setting) {
            return response()->json(['message' => 'Team settings not found'], Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($setting, $teamId, $data, $request) {
            $setting->verification_status = $data['status'];
            $setting->verification_notes = $data['notes'] ?? null;
            $setting->verified_at = $data['status'] === 'verified' ? now() : null;
            $setting->save();

            MsgTeamVerificationAudit::create([
                'team_id' => $teamId,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'changed_by_user_id' => optional($request->user())->id,
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Verification status updated']);
    }
}
