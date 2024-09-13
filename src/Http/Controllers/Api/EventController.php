<?php

namespace Prasso\Messaging\Http\Controllers\Api;


use App\Http\Controllers\Controller;use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventController extends Controller
{
    /**
     * List all events.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/events",
     *     tags={"Events"},
     *     summary="List all events",
     *     description="Retrieve a list of all events in the system.",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Annual Conference"),
     *                 @OA\Property(property="date", type="string", format="date", example="2024-12-10"),
     *                 @OA\Property(property="location", type="string", example="Main Hall")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No events found"
     *     )
     * )
     */
    public function index()
    {
        // Logic to retrieve all events
        // ...
        return response()->json(['events' => []]);  // Example response
    }

    /**
     * Create a new event.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/events",
     *     tags={"Events"},
     *     summary="Create a new event",
     *     description="Create a new event by providing event details.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "date", "location"},
     *             @OA\Property(property="name", type="string", example="Annual Conference"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-12-10"),
     *             @OA\Property(property="location", type="string", example="Main Hall")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Annual Conference"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-12-10"),
     *             @OA\Property(property="location", type="string", example="Main Hall")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request data"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
        ]);

        // Logic to create a new event
        // ...
        return response()->json(['event' => $validatedData], 201);
    }

    /**
     * View a specific event.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/events/{id}",
     *     tags={"Events"},
     *     summary="View a specific event",
     *     description="Retrieve details of a specific event by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Event ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Annual Conference"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-12-10"),
     *             @OA\Property(property="location", type="string", example="Main Hall")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found"
     *     )
     * )
     */
    public function show($id)
    {
        // Logic to retrieve a specific event by ID
        // ...
        return response()->json(['event' => ['id' => $id, 'name' => 'Sample Event', 'date' => '2024-12-10', 'location' => 'Main Hall']]);
    }

    /**
     * Update event details.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/events/{id}",
     *     tags={"Events"},
     *     summary="Update event details",
     *     description="Update details of an existing event by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Event ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Event Name"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-12-15"),
     *             @OA\Property(property="location", type="string", example="Updated Location")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Event Name"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-12-15"),
     *             @OA\Property(property="location", type="string", example="Updated Location")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'location' => 'sometimes|string|max:255',
        ]);

        // Logic to update event details
        // ...
        return response()->json(['event' => ['id' => $id, 'name' => 'Updated Event', 'date' => '2024-12-15', 'location' => 'Updated Location']]);
    }

    /**
     * Delete an event.
     *
     * @return \Illuminate\Http\Response
     * @OA\Delete(
     *     path="/api/events/{id}",
     *     tags={"Events"},
     *     summary="Delete an event",
     *     description="Delete an event by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Event ID"
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Event deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        // Logic to delete event
        // ...
        return response()->json(null, 204);
    }

    /**
     * Schedule event reminders.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/events/{id}/reminders",
     *     tags={"Events"},
     *     summary="Schedule event reminders",
     *     description="Schedule reminders for an event, to be sent before the event date.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Event ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reminder_date"},
     *             @OA\Property(property="reminder_date", type="string", format="date", example="2024-12-09")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reminder scheduled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reminder scheduled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found"
     *     )
     * )
     */
    public function scheduleReminders(Request $request, $id)
    {
        $validatedData = $request->validate([
            'reminder_date' => 'required|date|before:date',  // Assuming 'date' is the event date
        ]);

        // Logic to schedule reminders
        // ...
        return response()->json(['message' => 'Reminder scheduled successfully']);
    }
}
