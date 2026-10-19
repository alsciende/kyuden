<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A guest account just created (`POST /api/guests`), with its session token: the only time the token is sent.
 */
final readonly class GuestView
{
    public function __construct(
        public string $userId,
        public string $name,
        /** the session token of the guest, sent once: `Authorization: Bearer <token>` */
        public string $token,
    ) {
    }
}
