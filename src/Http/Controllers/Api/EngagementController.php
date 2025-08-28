<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;use Prasso\Messaging\Models\MsgEngagement;
use Prasso\Messaging\Models\MsgEngagementResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EngagementController extends Controller
{
    /**
     * Display a listing of all engagements (contests, polls, surveys).
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/engagements",
     *     tags={"Engagements"},
     *     summary="Get all engagements",
     *     description="Retrieve a list of all engagements, including contests, polls, and surveys.",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Weekly Survey"),
     *                 @OA\Property(property="type", type="string", example="survey"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T12:34:56Z")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $engagements = MsgEngagement::all();
        return response()->json($engagements);
    }

    /**
     * Store a newly created engagement.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/engagements",
     *     tags={"Engagements"},
     *     summary="Create a new engagement",
     *     description="Create a new engagement, such as a contest, poll, or survey.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "type"},
     *             @OA\Property(property="title", type="string", example="Weekly Survey"),
     *             @OA\Property(property="type", type="string", example="survey")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Engagement created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Weekly Survey"),
     *             @OA\Property(property="type", type="string", example="survey")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:contest,poll,survey',
        ]);

        $engagement = MsgEngagement::create($validatedData);

        return response()->json($engagement, Response::HTTP_CREATED);
    }

    /**
     * Display the specified engagement.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/engagements/{id}",
     *     tags={"Engagements"},
     *     summary="Get a specific engagement",
     *     description="Retrieve details of a specific engagement by its ID.",
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
     *             @OA\Property(property="title", type="string", example="Weekly Survey"),
     *             @OA\Property(property="type", type="string", example="survey")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Engagement not found"
     *     )
     * )
     */
    public function show($id)
    {
        $engagement = MsgEngagement::find($id);

        if (!$engagement) {
            return response()->json(['message' => 'Engagement not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($engagement);
    }

    /**
     * Update the specified engagement.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/engagements/{id}",
     *     tags={"Engagements"},
     *     summary="Update an engagement",
     *     description="Update the details of a specific engagement by its ID.",
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
     *             required={"title", "type"},
     *             @OA\Property(property="title", type="string", example="Updated Survey"),
     *             @OA\Property(property="type", type="string", example="survey")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Engagement updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Updated Survey"),
     *             @OA\Property(property="type", type="string", example="survey")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Engagement not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $engagement = MsgEngagement::find($id);

        if (!$engagement) {
            return response()->json(['message' => 'Engagement not found'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:contest,poll,survey',
        ]);

        $engagement->update($validatedData);

        return response()->json($engagement);
    }

    /**
     * Remove the specified engagement from storage.
     *
     * @return \Illuminate\Http\Response
     * @OA\Delete(
     *     path="/api/engagements/{id}",
     *     tags={"Engagements"},
     *     summary="Delete an engagement",
     *     description="Delete a specific engagement by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Engagement deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Engagement deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Engagement not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $engagement = MsgEngagement::find($id);

        if (!$engagement) {
            return response()->json(['message' => 'Engagement not found'], Response::HTTP_NOT_FOUND);
        }

        $engagement->delete();

        return response()->json(['message' => 'Engagement deleted successfully']);
    }

    /**
     * Record a guest's response to the engagement.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/engagements/{id}/responses",
     *     tags={"Engagements"},
     *     summary="Record a guest's response to an engagement",
     *     description="Record the response of a guest to a specific engagement, such as a poll, survey, or contest.",
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
     *             required={"guest_id", "response"},
     *             @OA\Property(property="guest_id", type="integer", example=1),
     *             @OA\Property(property="response", type="string", example="Yes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Response recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Response recorded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Engagement not found"
     *     )
     * )
     */
    public function recordResponse(Request $request, $id)
    {
        $validatedData = $request->validate([
            'guest_id' => 'required|integer|exists:msg_guests,id',
            'response' => 'required|string',
        ]);

        $engagement = MsgEngagement::find($id);

        if (!$engagement) {
            return response()->json(['message' => 'Engagement not found'], Response::HTTP_NOT_FOUND);
        }

        $response = new MsgEngagementResponse([
            'msg_engagement_id' => $id,
            'msg_guest_id' => $validatedData['guest_id'],
            'response' => $validatedData['response'],
        ]);

        $response->save();

        return response()->json(['message' => 'Response recorded successfully'], Response::HTTP_CREATED);
    }
}
