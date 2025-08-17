<?php

namespace Prasso\Messaging\Services;

use App\Models\User;
use Prasso\Messaging\Models\MsgGuest;

class RecipientResolver
{
    /**
     * Resolve arrays of user and guest IDs into a normalized list of recipients.
     *
     * @param array<int> $userIds
     * @param array<int> $guestIds
     * @return array<int, array{recipient_type:string, recipient_id:int, email:?string, phone:?string}>
     */
    public function resolve(array $userIds = [], array $guestIds = []): array
    {
        $recipients = [];

        if (!empty($userIds)) {
            $recipients = array_merge($recipients, $this->resolveUsers($userIds));
        }

        if (!empty($guestIds)) {
            $recipients = array_merge($recipients, $this->resolveGuests($guestIds));
        }

        return $recipients;
    }

    /**
     * @param array<int> $userIds
     * @return array<int, array{recipient_type:string, recipient_id:int, email:?string, phone:?string}>
     */
    public function resolveUsers(array $userIds): array
    {
        return User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->map(function (User $user) {
                return [
                    'recipient_type' => 'user',
                    'recipient_id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->getAttribute('phone'),
                ];
            })
            ->all();
    }

    /**
     * @param array<int> $guestIds
     * @return array<int, array{recipient_type:string, recipient_id:int, email:?string, phone:?string}>
     */
    public function resolveGuests(array $guestIds): array
    {
        return MsgGuest::query()
            ->whereIn('id', $guestIds)
            ->get()
            ->map(function (MsgGuest $guest) {
                return [
                    'recipient_type' => 'guest',
                    'recipient_id' => $guest->id,
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                ];
            })
            ->all();
    }
}
