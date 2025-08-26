<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgDelivery;

class TwilioStatusWebhookController
{
    public function handleStatus(Request $request)
    {
        // Twilio status callback fields (common): MessageSid, MessageStatus, ErrorCode, ErrorMessage, To, From
        $sid = $request->input('MessageSid');
        $status = strtolower((string) $request->input('MessageStatus'));
        $errorCode = $request->input('ErrorCode');
        $errorMessage = $request->input('ErrorMessage');

        Log::info('Twilio Status Callback', $request->all());

        if (empty($sid)) {
            return response('Missing MessageSid', 422);
        }

        $delivery = MsgDelivery::query()->where('provider_message_id', $sid)->first();
        if (! $delivery) {
            return response('Delivery not found', 404);
        }

        $update = [];
        switch ($status) {
            case 'queued':
            case 'accepted':
            case 'sending':
            case 'sent':
                $update['status'] = 'sent';
                $update['sent_at'] = $delivery->sent_at ?? now();
                break;
            case 'delivered':
                $update['status'] = 'delivered';
                $update['delivered_at'] = now();
                break;
            case 'undelivered':
            case 'failed':
                $update['status'] = 'failed';
                $update['failed_at'] = now();
                $update['error'] = $errorMessage ?: ($errorCode ? ('Twilio error ' . $errorCode) : 'delivery failed');
                break;
            default:
                // Keep last status but store raw for reference
                $update['metadata'] = array_merge((array) $delivery->metadata, [
                    'twilio_status' => $status,
                ]);
                break;
        }

        $delivery->update($update);

        return response('OK');
    }
}
