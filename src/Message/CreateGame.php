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
    ) {
    }

    /**
     * Creates a game whose random generator is seeded with fresh random bytes.
     */
    public static function withRandomSeed(string $gameId): self
    {
        return new self($gameId, bin2hex(random_bytes(32)));
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
}
