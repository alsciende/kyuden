<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * An invitation for a friend as anyone with its code sees it (`GET /api/invitations/{code}`): who invites, and whether
 * it can still be used (`pending`), or not (`expired`, `used`).
 */
final readonly class InvitationStatusView
{
    public function __construct(
        public string $hostName,
        public string $state,
    ) {
    }
}
