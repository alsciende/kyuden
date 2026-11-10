<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

#[AppAction(public: false)]
final readonly class CreateGame implements ActionInterface
{
    public function __construct(
        private string $gameId,
        private string $seed,
        private bool $friendly = false,
        private bool $mulligan = false,
    ) {
    }

    /**
     * Creates a game whose random generator is seeded with fresh random bytes.
     */
    public static function withRandomSeed(string $gameId, bool $friendly = false, bool $mulligan = false): self
    {
        return new self($gameId, bin2hex(random_bytes(32)), $friendly, $mulligan);
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    /**
     * Seed of the random generator of the game: 32 bytes, hex encoded.
     */
    public function getSeed(): string
    {
        return $this->seed;
    }

    /**
     * A friendly game (an invitation to it): not ranked.
     */
    public function isFriendly(): bool
    {
        return $this->friendly;
    }

    /**
     * The players mulligan during setup (not in the games of the house bot). False by default, so that the events of a
     * game created before the mulligan replay without it.
     */
    public function hasMulligan(): bool
    {
        return $this->mulligan;
    }
}
