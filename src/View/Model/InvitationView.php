<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\InvitationKind;

/**
 * An invitation of the authenticated user to a friendly game (`/api/invitations`, `/api/self/invitation`).
 */
final readonly class InvitationView
{
    public function __construct(
        public string $id,
        public InvitationKind $kind,
        /** the code a friend uses (`KX7-QM4-T9P`), for an invitation of a friend */
        public ?string $code,
        /** the bot of the host, for an invitation of their bot */
        public ?InvitationBotView $bot,
        /** the API key of the bot, limited to the invitation: only when it is created */
        public ?string $token,
        /** until when it waits for its guest */
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
