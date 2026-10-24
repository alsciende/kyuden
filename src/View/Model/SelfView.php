<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Clan;
use App\Model\UserSettings;

/**
 * The authenticated user (`GET /api/self`).
 */
final readonly class SelfView
{
    public function __construct(
        public string $userId,
        /** the name at court, seen by the other players */
        public string $name,
        /** how the human signs in, null for a guest or a bot */
        public ?string $username,
        /** a Great Clan, chosen with the account and changed at any time; null for a guest or a bot */
        public ?Clan $preferredClan,
        /** their preferences, e.g. how the web client draws the cards */
        public UserSettings $settings,
    ) {
    }
}
