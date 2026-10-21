<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Clan;

/**
 * An account a human has just created (`POST /api/accounts`) or signed in to (`POST /api/sessions`), with a new
 * session token: the only time the token is sent.
 */
final readonly class AccountView
{
    public function __construct(
        public string $userId,
        /** the name at court, seen by the other players */
        public string $name,
        /** how the human signs in, seen by them only */
        public string $username,
        /** a Great Clan, changed at any time */
        public ?Clan $preferredClan,
        /** the session token, sent once: `Authorization: Bearer <token>` */
        public string $token,
    ) {
    }
}
