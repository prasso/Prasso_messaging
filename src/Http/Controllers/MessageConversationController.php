<?php

namespace Prasso\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use Prasso\Messaging\Models\MsgMessage;
use Prasso\Messaging\Models\MsgInboundMessage;
use Prasso\Messaging\Models\MsgGuest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageConversationController extends Controller
{
    /**
     * Show all replies for a message across all deliveries
     */
    public function show($messageId)
    {
        $message = MsgMessage::with('deliveries')->findOrFail($messageId);
        
        // Authorization: only allow viewing if user owns the team
        $this->authorize('viewMessage', $message);
        
        // Get all delivery IDs for this message
        $deliveryIds = $message->deliveries()->pluck('id')->toArray();
        
        // Get all replies for all deliveries of this message
        $replies = MsgInboundMessage::whereIn('msg_delivery_id', $deliveryIds)
            ->with('guest')
            ->orderBy('received_at', 'asc')
            ->get();
        
        // Group replies by delivery for display
        $repliesByDelivery = $replies->groupBy('msg_delivery_id');
        
        return view('message-conversations.show', [
            'message' => $message,
            'replies' => $replies,
            'repliesByDelivery' => $repliesByDelivery,
        ]);
    }

    /**
     * Export all replies for a message as CSV
     */
    public function export($messageId)
    {
        $message = MsgMessage::with('deliveries')->findOrFail($messageId);
        
        // Authorization: only allow viewing if user owns the team
        $this->authorize('viewMessage', $message);
        
        // Get all delivery IDs for this message
        $deliveryIds = $message->deliveries()->pluck('id')->toArray();
        
        // Get all replies for all deliveries of this message
        $replies = MsgInboundMessage::whereIn('msg_delivery_id', $deliveryIds)
            ->with('guest')
            ->orderBy('received_at', 'asc')
            ->get();
        
        $filename = 'message-replies-' . $message->id . '-' . now()->format('Y-m-d-His') . '.csv';
        
        $response = new StreamedResponse(function () use ($message, $replies) {
            $handle = fopen('php://output', 'w');
            
            // Write CSV header
            fputcsv($handle, [
                'Sender Name',
                'Phone Number',
                'Message',
                'Received At',
                'Media Count',
            ]);
            
            // Write data rows
            foreach ($replies as $reply) {
                fputcsv($handle, [
                    $reply->guest?->name ?? 'Unknown',
                    $reply->from,
                    $reply->body,
                    $reply->received_at->format('Y-m-d H:i:s'),
                    count($reply->media ?? []),
                ]);
            }
            
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
        
        return $response;
    }
}
