<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A game in progress, which an account may watch (`GET /api/games`).
 */
final readonly class GameInProgressView
{
    /**
     * @param list<GameInProgressPlayerView> $players in player order of arrival
     */
    public function __construct(
        public string $id,
        public int $version,
        /** the place of the game: its phase and step */
        public ?string $place,
        /** a game of an invitation, not ranked */
        public bool $friendly,
        public array $players,
        /** the number of accounts watching it */
        public int $spectators,
    ) {
    }
}
