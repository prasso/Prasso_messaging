<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;use Prasso\Messaging\Models\MsgWorkflow;
use Prasso\Messaging\Models\MsgWorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkflowController extends Controller
{
    /**
     * Display a listing of all workflows.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/workflows",
     *     tags={"Workflows"},
     *     summary="Get all workflows",
     *     description="Retrieve a list of all workflows.",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="New Guest Workflow"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-30T12:34:56Z")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $workflows = MsgWorkflow::all();
        return response()->json($workflows);
    }

    /**
     * Store a newly created workflow.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/workflows",
     *     tags={"Workflows"},
     *     summary="Create a new workflow",
     *     description="Create a new workflow to automate tasks or messages.",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="New Guest Workflow")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Workflow created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="New Guest Workflow")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workflow = MsgWorkflow::create($validatedData);

        return response()->json($workflow, Response::HTTP_CREATED);
    }

    /**
     * Display the specified workflow.
     *
     * @return \Illuminate\Http\Response
     * @OA\Get(
     *     path="/api/workflows/{id}",
     *     tags={"Workflows"},
     *     summary="Get a specific workflow",
     *     description="Retrieve details of a specific workflow by its ID.",
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
     *             @OA\Property(property="name", type="string", example="New Guest Workflow")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workflow not found"
     *     )
     * )
     */
    public function show($id)
    {
        $workflow = MsgWorkflow::find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($workflow);
    }

    /**
     * Update the specified workflow.
     *
     * @return \Illuminate\Http\Response
     * @OA\Put(
     *     path="/api/workflows/{id}",
     *     tags={"Workflows"},
     *     summary="Update a workflow",
     *     description="Update the details of a specific workflow by its ID.",
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
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Updated Guest Workflow")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workflow updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Guest Workflow")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workflow not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $workflow = MsgWorkflow::find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workflow->update($validatedData);

        return response()->json($workflow);
    }

    /**
     * Remove the specified workflow from storage.
     *
     * @return \Illuminate\Http\Response
     * @OA\Delete(
     *     path="/api/workflows/{id}",
     *     tags={"Workflows"},
     *     summary="Delete a workflow",
     *     description="Delete a specific workflow by its ID.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workflow deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Workflow deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workflow not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $workflow = MsgWorkflow::find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found'], Response::HTTP_NOT_FOUND);
        }

        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted successfully']);
    }

    /**
     * Add steps to a workflow.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/workflows/{id}/steps",
     *     tags={"Workflows"},
     *     summary="Add steps to a workflow",
     *     description="Add specific steps to a workflow by its ID.",
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
     *             required={"steps"},
     *             @OA\Property(
     *                 property="steps",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"msg_messages_id"},
     *                     @OA\Property(property="msg_messages_id", type="integer", example=10),
     *                     @OA\Property(property="delay_in_minutes", type="integer", example=15)
     *                 ),
     *                 example={
     *                     {"msg_messages_id": 10, "delay_in_minutes": 0},
     *                     {"msg_messages_id": 11, "delay_in_minutes": 60}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Steps added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Steps added successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workflow not found"
     *     )
     * )
     */
    public function addSteps(Request $request, $id)
    {
        $workflow = MsgWorkflow::find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'steps' => 'required|array|min:1',
            'steps.*.msg_messages_id' => 'required|integer',
            'steps.*.delay_in_minutes' => 'nullable|integer|min:0',
        ]);

        $steps = $validated['steps'];

        foreach ($steps as $step) {
            MsgWorkflowStep::create([
                'msg_workflows_id' => $workflow->id,
                'msg_messages_id' => $step['msg_messages_id'],
                'delay_in_minutes' => $step['delay_in_minutes'] ?? 0,
            ]);
        }

        return response()->json(['message' => 'Steps added successfully'], Response::HTTP_CREATED);
    }

    /**
     * Start the workflow.
     *
     * @return \Illuminate\Http\Response
     * @OA\Post(
     *     path="/api/workflows/{id}/start",
     *     tags={"Workflows"},
     *     summary="Start a workflow",
     *     description="Start the execution of a workflow.",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workflow started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Workflow started successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workflow not found"
     *     )
     * )
     */
    public function start($id)
    {
        $workflow = MsgWorkflow::find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found'], Response::HTTP_NOT_FOUND);
        }

        // Logic to start the workflow would go here.

        return response()->json(['message' => 'Workflow started successfully']);
    }
}
