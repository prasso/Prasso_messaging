<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prasso\Messaging\Models\MsgInboundMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboundMessageController extends Controller
{
    public function index(Request $request)
    {
        $query = MsgInboundMessage::query()
            ->with('guest')
            ->orderByDesc('received_at');

        if ($request->filled('phone')) {
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone'));
            $query->where('from', 'like', "%{$phone}");
        }
        if ($request->filled('from_date')) {
            $query->where('received_at', '>=', $request->date('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('received_at', '<=', $request->date('to_date'));
        }

        $perPage = (int) $request->input('per_page', 25);
        return response()->json($query->paginate($perPage));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'inbound_messages_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id', 'guest_id', 'from', 'to', 'body', 'media_count', 'provider_message_id', 'received_at', 'created_at'
            ]);

            $query = MsgInboundMessage::query()->orderBy('id');
            if ($request->filled('from_date')) {
                $query->where('received_at', '>=', $request->date('from_date'));
            }
            if ($request->filled('to_date')) {
                $query->where('received_at', '<=', $request->date('to_date'));
            }

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->msg_guest_id,
                        $row->from,
                        $row->to,
                        $row->body,
                        is_array($row->media) ? count($row->media) : 0,
                        $row->provider_message_id,
                        optional($row->received_at)->toDateTimeString(),
                        optional($row->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }
}
