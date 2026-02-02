<?php

namespace Prasso\Messaging\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Prasso\Messaging\Models\MsgDelivery;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Models\MsgInboundMessage;
use Prasso\Messaging\Models\MsgTeamSetting;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge'));

        $expected = config('messaging.whatsapp.webhook_verify_token')
            ?: env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');

        if ($mode === 'subscribe' && !empty($expected) && hash_equals($expected, $token)) {
            return response($challenge, Response::HTTP_OK);
        }

        return response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    public function handle(Request $request)
    {
        $this->verifySignatureIfConfigured($request);

        $payload = $request->all();

        try {
            $entries = (array) ($payload['entry'] ?? []);
            foreach ($entries as $entry) {
                $changes = (array) ($entry['changes'] ?? []);
                foreach ($changes as $change) {
                    $value = (array) ($change['value'] ?? []);
                    $metadata = (array) ($value['metadata'] ?? []);
                    $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');

                    $teamId = $this->resolveTeamId($phoneNumberId);

                    $this->handleStatuses($value);
                    $this->handleMessages($value, $teamId);
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing error', ['error' => $e->getMessage()]);
            Log::error($e);
        }

        return response()->json(['ok' => true]);
    }

    protected function handleStatuses(array $value): void
    {
        $statuses = (array) ($value['statuses'] ?? []);

        foreach ($statuses as $statusItem) {
            $providerId = (string) ($statusItem['id'] ?? '');
            $status = strtolower((string) ($statusItem['status'] ?? ''));
            $timestamp = (int) ($statusItem['timestamp'] ?? 0);

            if ($providerId === '') {
                continue;
            }

            $delivery = MsgDelivery::query()->where('provider_message_id', $providerId)->first();
            if (!$delivery) {
                continue;
            }

            $ts = $timestamp > 0 ? now()->setTimestamp($timestamp) : now();

            if ($status === 'delivered') {
                $delivery->update([
                    'status' => 'delivered',
                    'delivered_at' => $ts,
                ]);
                continue;
            }

            if ($status === 'read') {
                $delivery->update([
                    'status' => 'delivered',
                    'delivered_at' => $delivery->delivered_at ?: $ts,
                ]);
                continue;
            }

            if ($status === 'sent') {
                $delivery->update([
                    'status' => 'sent',
                    'sent_at' => $delivery->sent_at ?: $ts,
                ]);
                continue;
            }

            if ($status === 'failed') {
                $err = $statusItem['errors'][0] ?? null;
                $title = is_array($err) ? ($err['title'] ?? null) : null;
                $msg = is_array($err) ? ($err['message'] ?? null) : null;

                $delivery->update([
                    'status' => 'failed',
                    'error' => trim((string) (($title ? ($title . ': ') : '') . ($msg ?: 'WhatsApp delivery failed'))),
                    'failed_at' => $ts,
                ]);
            }
        }
    }

    protected function handleMessages(array $value, ?int $teamId): void
    {
        $messages = (array) ($value['messages'] ?? []);
        if (empty($messages)) {
            return;
        }

        foreach ($messages as $msg) {
            $from = (string) ($msg['from'] ?? '');
            $providerMessageId = (string) ($msg['id'] ?? '');
            $timestamp = (int) ($msg['timestamp'] ?? 0);
            $receivedAt = $timestamp > 0 ? now()->setTimestamp($timestamp) : now();

            $body = null;
            $text = (array) ($msg['text'] ?? []);
            if (!empty($text)) {
                $body = $text['body'] ?? null;
            }

            $context = (array) ($msg['context'] ?? []);
            $replyToProviderId = (string) ($context['id'] ?? '');

            $deliveryId = null;
            if ($replyToProviderId !== '') {
                $deliveryId = MsgDelivery::query()->where('provider_message_id', $replyToProviderId)->value('id');
            }

            $guestId = null;
            if (!empty($teamId) && $from !== '') {
                $hash = $this->hashPhone($from);
                if (!empty($hash)) {
                    $guestId = MsgGuest::query()
                        ->where('team_id', $teamId)
                        ->where('phone_hash', $hash)
                        ->value('id');
                }
            }

            // Upsert to prevent duplicates on provider_message_id
            MsgInboundMessage::query()->updateOrCreate(
                ['provider_message_id' => $providerMessageId],
                [
                    'team_id' => $teamId,
                    'msg_guest_id' => $guestId,
                    'msg_delivery_id' => $deliveryId,
                    'from' => $from,
                    'to' => (string) (($value['metadata']['display_phone_number'] ?? '') ?: null),
                    'body' => $body,
                    'media' => null,
                    'received_at' => $receivedAt,
                    'raw' => $msg,
                ]
            );
        }
    }

    protected function resolveTeamId(string $phoneNumberId): ?int
    {
        if ($phoneNumberId === '') {
            return null;
        }

        return MsgTeamSetting::query()
            ->where('whatsapp_phone_number_id', $phoneNumberId)
            ->value('team_id');
    }

    protected function verifySignatureIfConfigured(Request $request): void
    {
        $appSecret = config('messaging.whatsapp.app_secret') ?: env('WHATSAPP_APP_SECRET');
        if (empty($appSecret)) {
            return;
        }

        $sig = (string) $request->header('X-Hub-Signature-256', '');
        if (!str_starts_with($sig, 'sha256=')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
        if (!hash_equals($expected, $sig)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    protected function hashPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }
        if ($digits === '') {
            return '';
        }

        return hash('sha256', $digits);
    }
}
