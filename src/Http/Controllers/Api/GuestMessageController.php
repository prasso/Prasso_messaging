<?php

namespace Prasso\Messaging\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use Prasso\Messaging\Models\MsgGuestMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuestMessageController extends Controller
{
    /**
     * Display a listing of all guest messages.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/guest-messages",
     *     tags={"Guest Messages"},
     *     summary="Get all guest messages",
     *     description="Retrieve a list of all messages sent to guests.",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="guest_id", type="integer", example=1),
     *                 @OA\Property(property="message", type="string", example="Welcome to our service!"),
     *                 @OA\Property(property="status", type="string", example="sent"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T12:34:56Z")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $guestMessages = MsgGuestMessage::all();
        return response()->json($guestMessages);
    }

    /**
     * Log a newly sent guest message.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/guest-messages",
     *     tags={"Guest Messages"},
     *     summary="Log a new guest message",
     *     description="Create a new guest message record after a message has been sent.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"guest_id", "message", "status"},
     *             @OA\Property(property="guest_id", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="Welcome to our service!"),
     *             @OA\Property(property="status", type="string", example="sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message logged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="guest_id", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="Welcome to our service!"),
     *             @OA\Property(property="status", type="string", example="sent")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'guest_id' => 'required|integer|exists:msg_guests,id',
            'message' => 'required|string',
            'status' => 'required|string|in:sent,failed',
        ]);

        $guestMessage = MsgGuestMessage::create($validatedData);

        return response()->json($guestMessage, Response::HTTP_CREATED);
    }

    /**
     * Display the specified guest message.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/guest-messages/{id}",
     *     tags={"Guest Messages"},
     *     summary="Get a specific guest message",
     *     description="Retrieve details of a specific guest message by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="guest_id", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="Welcome to our service!"),
     *             @OA\Property(property="status", type="string", example="sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guest message not found"
     *     )
     * )
     */
    public function show($id)
    {
        $guestMessage = MsgGuestMessage::find($id);

        if (!$guestMessage) {
            return response()->json(['message' => 'Guest message not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($guestMessage);
    }

    /**
     * Update the specified guest message log.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/guest-messages/{id}",
     *     tags={"Guest Messages"},
     *     summary="Update guest message log",
     *     description="Update details of a guest message log, such as status (e.g., mark as sent or failed).",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest message updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="guest_id", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="Welcome to our service!"),
     *             @OA\Property(property="status", type="string", example="sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guest message not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $guestMessage = MsgGuestMessage::find($id);

        if (!$guestMessage) {
            return response()->json(['message' => 'Guest message not found'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validate([
            'status' => 'required|string|in:sent,failed',
        ]);

        $guestMessage->update($validatedData);

        return response()->json($guestMessage);
    }

    /**
     * Remove the specified guest message log from storage.
     *
     * @return \Illuminate\Http\Response
     * @OA\Delete(
     *     path="/api/guest-messages/{id}",
     *     tags={"Guest Messages"},
     *     summary="Delete a guest message log",
     *     description="Delete a specific guest message log by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest message deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guest message deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guest message not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $guestMessage = MsgGuestMessage::find($id);

        if (!$guestMessage) {
            return response()->json(['message' => 'Guest message not found'], Response::HTTP_NOT_FOUND);
        }

        $guestMessage->delete();

        return response()->json(['message' => 'Guest message deleted successfully']);
    }
}
