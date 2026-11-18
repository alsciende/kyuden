<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Model\Banner;

/**
 * A user in the view of a tournament: its organizer, a player registered (never their deck nor their clan).
 */
final readonly class TournamentUserView
{
    public function __construct(
        public string $userId,
        /** their name at court */
        public string $name,
        /** their nobori: a mon on a pattern */
        public Banner $banner,
    ) {
    }
}
