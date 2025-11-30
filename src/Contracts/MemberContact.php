<?php

namespace Prasso\Messaging\Contracts;

/**
 * Abstraction for a member-like contact that Messaging can send to, without
 * depending on a specific package's model implementation.
 *
 * Implement this interface on your domain Member model so Messaging can
 * consistently retrieve contact details.
 */
interface MemberContact
{
    /**
     * Unique identifier of the member/contact.
     *
     * @return int|string
     */
    public function getMemberId();

    /**
     * Primary email address for email channel. Return null if none.
     */
    public function getMemberEmail(): ?string;

    /**
     * Primary phone number for SMS channel. Return null if none.
     * Raw/unformatted is fine; Messaging will normalize.
     */
    public function getMemberPhone(): ?string;

    /**
     * Full display name for token replacement. Return null if unknown.
     */
    public function getMemberDisplayName(): ?string;
}
