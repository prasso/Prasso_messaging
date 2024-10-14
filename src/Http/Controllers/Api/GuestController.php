<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Prasso\Messaging\Models\MsgGuest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * **Guest Management**
 * 
 * 
 */
class GuestController extends Controller
{
    /**
 * Display a listing of all guests.
 *
 * @return \Illuminate\Http\Response
 * @OA\Get(
 *     path="/api/guests",
 *     tags={"Guests"},
 *     summary="Get all guests",
 *     description="Retrieve a list of all guests.",
 *     security={{"bearer_token":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="John Doe"),
 *                 @OA\Property(property="email", type="string", example="johndoe@example.com"),
 *                 @OA\Property(property="phone", type="string", example="123-456-7890"),
 *                 @OA\Property(property="is_converted", type="boolean", example=false)
 *             )
 *         )
 *     )
 * )
 */
    public function index()
    {
        $guests = MsgGuest::all();
        return response()->json($guests);
    }

    /**
     * Store a newly created guest in storage.
    *
    * @return \Illuminate\Http\Response
    * @OA\Post(
    *     path="/api/guests",
    *     tags={"Guests"},
    *     summary="Create a new guest",
    *     description="Add a new guest to the system.",
    *     security={{"bearer_token":{}}},
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(
    *             required={"name", "email"},
    *             @OA\Property(property="name", type="string", example="Jane Doe"),
    *             @OA\Property(property="email", type="string", example="janedoe@example.com"),
    *             @OA\Property(property="phone", type="string", example="123-456-7890")
    *         )
    *     ),
    *     @OA\Response(
    *         response=201,
    *         description="Guest created successfully",
    *         @OA\JsonContent(
    *             @OA\Property(property="id", type="integer", example=1),
    *             @OA\Property(property="name", type="string", example="Jane Doe"),
    *             @OA\Property(property="email", type="string", example="janedoe@example.com"),
    *             @OA\Property(property="phone", type="string", example="123-456-7890")
    *         )
    *     )
    * )
    */
    public function store(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:msg_guests,email',
            'phone' => 'nullable|string|max:20',
        ]);

        // Create the guest
        $guest = MsgGuest::create($validatedData);

        return response()->json($guest, Response::HTTP_CREATED);
    }

    /**
     * Display the specified guest.
    * Display a specific guest by ID.
    *
    * @return \Illuminate\Http\Response
    * @OA\Get(
    *     path="/api/guests/{id}",
    *     tags={"Guests"},
    *     summary="Get a specific guest",
    *     description="Retrieve a single guest by their ID.",
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
    *             @OA\Property(property="name", type="string", example="Jane Doe"),
    *             @OA\Property(property="email", type="string", example="janedoe@example.com"),
    *             @OA\Property(property="phone", type="string", example="123-456-7890")
    *         )
    *     ),
    *     @OA\Response(
    *         response=404,
    *         description="Guest not found"
    *     )
    * )
    */
    public function show($id)
    {
        // Find the guest by ID
        $guest = MsgGuest::find($id);

        // Return 404 if guest not found
        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($guest);
    }

    /**
     * Update a specific guest's details.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/guests/{id}",
     *     tags={"Guests"},
     *     summary="Update guest details",
     *     description="Update the details of a specific guest by ID.",
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
     *             @OA\Property(property="name", type="string", example="John Smith"),
     *             @OA\Property(property="email", type="string", example="johnsmith@example.com"),
     *             @OA\Property(property="phone", type="string", example="987-654-3210")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Smith"),
     *             @OA\Property(property="email", type="string", example="johnsmith@example.com"),
     *             @OA\Property(property="phone", type="string", example="987-654-3210")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guest not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        // Find the guest by ID
        $guest = MsgGuest::find($id);

        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:msg_guests,email,' . $id,
            'phone' => 'nullable|string|max:20',
        ]);

        // Update the guest
        $guest->update($validatedData);

        return response()->json($guest);
    }

    /**
     * Remove the specified guest from storage.
 *
 * @return \Illuminate\Http\Response
 * @OA\Delete(
 *     path="/api/guests/{id}",
 *     tags={"Guests"},
 *     summary="Delete a guest",
 *     description="Delete a guest by their ID.",
 *     security={{"bearer_token":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Guest deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Guest deleted")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Guest not found"
 *     )
 * )
 */

    public function destroy($id)
    {
        // Find the guest by ID
        $guest = MsgGuest::find($id);

        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], Response::HTTP_NOT_FOUND);
        }

        // Delete the guest
        $guest->delete();

        return response()->json(['message' => 'Guest deleted'], Response::HTTP_OK);
    }


    /**
     * Mark a guest as converted.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/guests/{id}/convert",
     *     tags={"Guests"},
     *     summary="Convert a guest",
     *     description="Mark a guest as converted (e.g., first-time guest to regular attendee).",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest converted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guest converted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guest not found"
     *     )
     * )
     */
    public function convert($id)
    {
        // Find the guest by ID
        $guest = MsgGuest::find($id);

        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], Response::HTTP_NOT_FOUND);
        }

        // Mark the guest as converted
        $guest->is_converted = true;
        $guest->save();

        return response()->json(['message' => 'Guest converted successfully']);
    }

    /**
     * Engage a guest with a message or campaign.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/guests/{id}/engage",
     *     tags={"Guests"},
     *     summary="Engage a guest with a message or campaign",
     *     description="Engage a guest by sending a message or enrolling them in a campaign.",
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
     *             required={"message_id"},
     *             @OA\Property(property="message_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest engaged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guest engaged successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guest or message not found"
     *     )
     * )
     */
    public function engage(Request $request, $id)
    {
        // Find the guest by ID
        $guest = MsgGuest::find($id);

        if (!$guest) {
            return response()->json(['message' => 'Guest not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate the request data for engagement
        $validatedData = $request->validate([
            'message_id' => 'required|exists:msg_messages,id',  // Message or campaign ID
        ]);

        // You can customize this logic depending on how you engage guests (e.g., sending a message)
        // For simplicity, let's assume we're just logging that the guest has been engaged.

        // Create a guest engagement (e.g., in a `MsgGuestMessage` model, but not shown here)
        // MsgGuestMessage::create([
        //     'guest_id' => $id,
        //     'message_id' => $validatedData['message_id'],
        //     'is_sent' => true,
        // ]);

        return response()->json(['message' => 'Guest engaged successfully']);
    }
}
