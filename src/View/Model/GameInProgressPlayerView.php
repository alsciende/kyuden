<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Clan;

/**
 * A player of a game in progress, in the list of the games to watch (GameInProgressView).
 */
final readonly class GameInProgressPlayerView
{
    public function __construct(
        public string $userId,
        /** their name at court */
        public string $name,
        /** the clan of their stronghold */
        public ?Clan $clan,
        /** a program plays for them (the house bot, the bot of an account) */
        public bool $bot,
    ) {
    }
}
