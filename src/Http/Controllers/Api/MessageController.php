<?php

namespace Prasso\Messaging\Http\Controllers\Api;


use App\Http\Controllers\Controller;use Prasso\Messaging\Models\MsgMessage;
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
            'type' => 'required|string|in:email,sms',
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
            'type' => 'sometimes|required|string|in:email,sms',
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
     *     description="Send a specific message to selected guests based on provided guest IDs.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message_id", "guest_ids"},
     *             @OA\Property(property="message_id", type="integer", example=1),
     *             @OA\Property(property="guest_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Message sent successfully to selected guests")
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
            'guest_ids' => 'required|array',
            'guest_ids.*' => 'integer|exists:guests,id',
        ]);

        // Logic for sending message to guests (implementation details depend on your application)
        // ...

        return response()->json(['message' => 'Message sent successfully to selected guests']);
    }
}
