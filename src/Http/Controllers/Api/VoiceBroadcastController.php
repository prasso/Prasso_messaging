<?php

namespace Prasso\Messaging\Http\Controllers\Api;


use App\Http\Controllers\Controller;use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VoiceBroadcastController extends Controller
{
    /**
     * Send voice broadcast to guests.
     *
     * @OA\Post(
     *     path="/api/voice-broadcasts/send",
     *     tags={"Voice Broadcasts"},
     *     summary="Send a voice broadcast to selected guests",
     *     description="Send a voice broadcast message to a list of guests. You can specify the message, and the list of guests by IDs.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message", "guest_ids"},
     *             @OA\Property(property="message", type="string", example="This is a test broadcast message"),
     *             @OA\Property(
     *                 property="guest_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Voice broadcast sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Broadcast sent successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request data"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to send broadcast"
     *     )
     * )
     */
    public function send(Request $request)
    {
        $validatedData = $request->validate([
            'message' => 'required|string|max:255',
            'guest_ids' => 'required|array',
            'guest_ids.*' => 'integer|exists:guests,id',
        ]);

        $message = $validatedData['message'];
        $guestIds = $validatedData['guest_ids'];

        // Logic to send the voice broadcast to the specified guests
        // e.g., using a voice broadcasting service

        // Placeholder for actual broadcast sending logic
        $broadcastSuccess = true;

        if ($broadcastSuccess) {
            return response()->json([
                'status' => 'success',
                'message' => 'Broadcast sent successfully',
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send broadcast',
            ], 500);
        }
    }
}
