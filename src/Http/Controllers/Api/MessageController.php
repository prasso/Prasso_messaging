<?php

namespace Prasso\Messaging\Http\Controllers\Api;


use App\Http\Controllers\Controller;use Prasso\Messaging\Models\MsgMessage;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Services\RecipientResolver;
use Prasso\Messaging\Jobs\ProcessMsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageController extends Controller
{
    /**
     * Display a listing of all messages.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/messages",
     *     tags={"Messages"},
     *     summary="Get all messages",
     *     description="Retrieve a list of all messages.",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="subject", type="string", example="Event Reminder"),
     *                 @OA\Property(property="body", type="string", example="Don't forget to attend the event tomorrow."),
     *                 @OA\Property(property="type", type="string", example="email"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T12:34:56Z")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $messages = MsgMessage::all();
        return response()->json($messages);
    }

    /**
     * Store a newly created message.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/messages",
     *     tags={"Messages"},
     *     summary="Create a new message",
     *     description="Create and store a new message.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subject", "body", "type"},
     *             @OA\Property(property="subject", type="string", example="Event Reminder"),
     *             @OA\Property(property="body", type="string", example="Don't forget to attend the event tomorrow."),
     *             @OA\Property(property="type", type="string", example="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="subject", type="string", example="Event Reminder"),
     *             @OA\Property(property="body", type="string", example="Don't forget to attend the event tomorrow."),
     *             @OA\Property(property="type", type="string", example="email")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'required|string|in:email,sms,push,inapp',
        ]);

        // Create the message
        $message = MsgMessage::create($validatedData);

        return response()->json($message, Response::HTTP_CREATED);
    }

    /**
     * Display the specified message.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/messages/{id}",
     *     tags={"Messages"},
     *     summary="Get a specific message",
     *     description="Retrieve a single message by its ID.",
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
     *             @OA\Property(property="subject", type="string", example="Event Reminder"),
     *             @OA\Property(property="body", type="string", example="Don't forget to attend the event tomorrow."),
     *             @OA\Property(property="type", type="string", example="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found"
     *     )
     * )
     */
    public function show($id)
    {
        $message = MsgMessage::find($id);

        if (!$message) {
            return response()->json(['message' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($message);
    }

    /**
     * Update the specified message in storage.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/messages/{id}",
     *     tags={"Messages"},
     *     summary="Update message details",
     *     description="Update the details of a specific message.",
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
     *             @OA\Property(property="subject", type="string", example="Event Reminder"),
     *             @OA\Property(property="body", type="string", example="Don't forget to attend the event tomorrow."),
     *             @OA\Property(property="type", type="string", example="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="subject", type="string", example="Updated Subject"),
     *             @OA\Property(property="body", type="string", example="Updated Body"),
     *             @OA\Property(property="type", type="string", example="sms")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $message = MsgMessage::find($id);

        if (!$message) {
            return response()->json(['message' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate the request data
        $validatedData = $request->validate([
            'subject' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'type' => 'sometimes|required|string|in:email,sms,push,inapp',
        ]);

        // Update the message
        $message->update($validatedData);

        return response()->json($message);
    }

    /**
     * Remove the specified message from storage.
     *
     * @return \Illuminate\Http\Response
     * @OA\Delete(
     *     path="/api/messages/{id}",
     *     tags={"Messages"},
     *     summary="Delete a message",
     *     description="Delete a specific message by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Message deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $message = MsgMessage::find($id);

        if (!$message) {
            return response()->json(['message' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted']);
    }

    /**
     * Send a message to selected guests.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/messages/send",
     *     tags={"Messages"},
     *     summary="Send a message to selected guests",
     *     description="Send a specific message to selected recipients. Supports guests and users.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message_id"},
     *             @OA\Property(property="message_id", type="integer", example=1),
     *             @OA\Property(property="guest_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="user_ids", type="array", @OA\Items(type="integer"), example={10, 11})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Accepted for processing",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Queued deliveries"),
     *             @OA\Property(property="queued", type="integer", example=3),
     *             @OA\Property(property="skipped", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message or guest not found"
     *     )
     * )
     */
    public function send(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'message_id' => 'required|integer|exists:msg_messages,id',
            'guest_ids' => 'array',
            'guest_ids.*' => 'integer|exists:msg_guests,id',
            'user_ids' => 'array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if (empty($validatedData['guest_ids'] ?? []) && empty($validatedData['user_ids'] ?? [])) {
            return response()->json([
                'message' => 'At least one of guest_ids or user_ids must be provided.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = MsgMessage::find($validatedData['message_id']);
        if (! $message) {
            return response()->json(['message' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        // Resolve recipients (users and guests)
        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolve(
            $validatedData['user_ids'] ?? [],
            $validatedData['guest_ids'] ?? []
        );

        $queued = 0;
        $skipped = 0;

        foreach ($recipients as $recipient) {
            [$status, $error] = $this->determineStatusForChannel($message->type, $recipient);

            $delivery = MsgDelivery::create([
                'msg_message_id' => $message->id,
                'recipient_type' => $recipient['recipient_type'],
                'recipient_id' => $recipient['recipient_id'],
                'channel' => $message->type,
                'status' => $status,
                'error' => $error,
                'metadata' => [
                    'subject' => $message->subject,
                    'preview' => mb_substr($message->body, 0, 120),
                ],
            ]);

            if ($status === 'queued') {
                ProcessMsgDelivery::dispatch($delivery->id);
                $queued++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'message' => 'Queued deliveries',
            'queued' => $queued,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Determine if recipient has the required contact for the channel.
     * Returns [status, error].
     *
     * @param string $channel
     * @param array{recipient_type:string, recipient_id:int, email:?string, phone:?string} $recipient
     * @return array{0:string,1:?string}
     */
    protected function determineStatusForChannel(string $channel, array $recipient): array
    {
        switch ($channel) {
            case 'email':
                if (!empty($recipient['email'])) {
                    return ['queued', null];
                }
                return ['skipped', 'missing email'];
            case 'sms':
                if (!empty($recipient['phone'])) {
                    // If recipient is a guest, enforce subscription status
                    if (($recipient['recipient_type'] ?? null) === 'guest') {
                        $guest = MsgGuest::query()->find($recipient['recipient_id']);
                        if ($guest && $guest->is_subscribed === false) {
                            return ['skipped', 'unsubscribed'];
                        }
                    }
                    return ['queued', null];
                }
                return ['skipped', 'missing phone'];
            case 'push':
                // No push token resolution implemented yet
                return ['skipped', 'push channel not configured'];
            case 'inapp':
                if ($recipient['recipient_type'] === 'user') {
                    return ['queued', null];
                }
                return ['skipped', 'in-app only supported for users'];
            default:
                return ['skipped', 'unknown channel'];
        }
    }
}
