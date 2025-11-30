<?php

namespace Prasso\Messaging\Services;

use App\Models\User;
use Prasso\Messaging\Models\MsgGuest;
use Prasso\Messaging\Contracts\MemberContact;

class RecipientResolver
{
    /**
     * Resolve arrays of user, guest, and member IDs into a normalized list of recipients.
     *
     * @param array<int> $userIds
     * @param array<int> $guestIds
     * @param array<int> $memberIds
     * @return array<int, array{recipient_type:string, recipient_id:int, email:?string, phone:?string}>
     */
    public function resolve(array $userIds = [], array $guestIds = [], array $memberIds = []): array
    {
        $recipients = [];

        if (!empty($userIds)) {
            $recipients = array_merge($recipients, $this->resolveUsers($userIds));
        }

        if (!empty($guestIds)) {
            $recipients = array_merge($recipients, $this->resolveGuests($guestIds));
        }

        if (!empty($memberIds)) {
            $recipients = array_merge($recipients, $this->resolveMembers($memberIds));
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

    /**
     * @param array<int> $memberIds
     * @return array<int, array{recipient_type:string, recipient_id:int, email:?string, phone:?string}>
     */
    public function resolveMembers(array $memberIds): array
    {
        $memberModel = config('messaging.member_model');
        if (!is_string($memberModel) || !class_exists($memberModel)) {
            return [];
        }

        return $memberModel::query()
            ->whereIn('id', $memberIds)
            ->get()
            ->map(function ($member) {
                if ($member instanceof MemberContact) {
                    return [
                        'recipient_type' => 'member',
                        'recipient_id' => (int) ($member->getMemberId()),
                        'email' => $member->getMemberEmail(),
                        'phone' => $member->getMemberPhone(),
                    ];
                }

                // Fallback to common attributes
                return [
                    'recipient_type' => 'member',
                    'recipient_id' => (int) ($member->id ?? $member->getAttribute('id')),
                    'email' => $member->email ?? ($member->getAttribute('email') ?? null),
                    'phone' => $member->phone ?? ($member->getAttribute('phone') ?? null),
                ];
            })
            ->all();
    }
}
