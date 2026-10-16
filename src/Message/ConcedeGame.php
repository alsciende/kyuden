<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The player concedes the game, which ends at once: their opponent wins. Accepted at any time once the game has
 * started, whoever has the opportunity to act. The reason is read by the developers only, never shown to the other
 * players: report there a bug that prevents the player from going on.
 */
#[AppAction]
final readonly class ConcedeGame implements PlayerActionInterface
{
    public const int MAX_REASON_LENGTH = 2000;

    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Why the player concedes, e.g. a bug of the engine; read by the developers only.')]
        #[Assert\Length(max: self::MAX_REASON_LENGTH)]
        private ?string $reason = null,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
