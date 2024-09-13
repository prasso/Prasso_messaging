<?php

namespace Prasso\Messaging\Http\Controllers\Api;


use App\Http\Controllers\Controller;use Prasso\Messaging\Models\MsgCampaign;
use Prasso\Messaging\Models\MsgCampaignMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CampaignController extends Controller
{
    /**
     * Display a listing of all campaigns.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/campaigns",
     *     tags={"Campaigns"},
     *     summary="Get all campaigns",
     *     description="Retrieve a list of all campaigns.",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Easter Event Campaign"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T12:34:56Z")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $campaigns = MsgCampaign::all();
        return response()->json($campaigns);
    }

    /**
     * Store a newly created campaign.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/campaigns",
     *     tags={"Campaigns"},
     *     summary="Create a new campaign",
     *     description="Create and store a new campaign.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Easter Event Campaign")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Campaign created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Easter Event Campaign"),
     *             @OA\Property(property="status", type="string", example="draft")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $campaign = MsgCampaign::create($validatedData);

        return response()->json($campaign, Response::HTTP_CREATED);
    }

    /**
     * Display the specified campaign.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/campaigns/{id}",
     *     tags={"Campaigns"},
     *     summary="Get a specific campaign",
     *     description="Retrieve a single campaign by its ID.",
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
     *             @OA\Property(property="name", type="string", example="Easter Event Campaign"),
     *             @OA\Property(property="status", type="string", example="draft")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found"
     *     )
     * )
     */
    public function show($id)
    {
        $campaign = MsgCampaign::find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($campaign);
    }

    /**
     * Update the specified campaign.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/campaigns/{id}",
     *     tags={"Campaigns"},
     *     summary="Update campaign details",
     *     description="Update the details of a specific campaign.",
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
     *             @OA\Property(property="name", type="string", example="Updated Campaign Name")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Campaign Name"),
     *             @OA\Property(property="status", type="string", example="draft")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $campaign = MsgCampaign::find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        $campaign->update($validatedData);

        return response()->json($campaign);
    }

    /**
     * Remove the specified campaign from storage.
     *
     * @return \Illuminate\Http\Response
     * @OA\Delete(
     *     path="/api/campaigns/{id}",
     *     tags={"Campaigns"},
     *     summary="Delete a campaign",
     *     description="Delete a specific campaign by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Campaign deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $campaign = MsgCampaign::find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted']);
    }

    /**
     * Add a message to a campaign.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/campaigns/{id}/messages",
     *     tags={"Campaigns"},
     *     summary="Add a message to a campaign",
     *     description="Associate a message with a campaign.",
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
     *         description="Message added to campaign successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Message added to campaign successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign or Message not found"
     *     )
     * )
     */
    public function addMessage(Request $request, $id)
    {
        $validatedData = $request->validate([
            'message_id' => 'required|integer|exists:msg_messages,id',
        ]);

        $campaign = MsgCampaign::find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        $campaignMessage = new MsgCampaignMessage([
            'campaign_id' => $id,
            'message_id' => $validatedData['message_id']
        ]);

        $campaignMessage->save();

        return response()->json(['message' => 'Message added to campaign successfully']);
    }

    /**
     * Launch the campaign and send scheduled messages.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/campaigns/{id}/launch",
     *     tags={"Campaigns"},
     *     summary="Launch a campaign",
     *     description="Launch the campaign and send all scheduled messages.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Campaign launched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Campaign launched successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Campaign not found"
     *     )
     * )
     */
    public function launch($id)
    {
        $campaign = MsgCampaign::find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        // Logic to send all scheduled messages for the campaign
        // This could involve calling a job, or using a messaging service
        // ...

        return response()->json(['message' => 'Campaign launched successfully']);
    }
}
