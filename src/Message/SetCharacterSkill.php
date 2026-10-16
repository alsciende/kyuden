<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\ConflictType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Records the military or political skill of a character in play, every modifier applied (an event, an attachment,
 * another effect), so that the players see its current skill: the engine does not apply card effects. It replaces the
 * skill set before, until `ResetCharacterSkill` or the character leaves play.
 *
 * The engine does not compute the totals of a conflict from it: they are declared by the players
 * (`DeclareConflictSkill`). The character can belong to any player.
 */
#[AppAction]
final readonly class SetCharacterSkill implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in play.')]
        private string $cardId,
        #[Description('Skill to set.')]
        private ConflictType $type,
        #[Description('Skill of the character, every modifier applied.')]
        #[Assert\PositiveOrZero]
        private int $skill,
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

    public function getCardId(): string
    {
        return $this->cardId;
    }

    public function getType(): ConflictType
    {
        return $this->type;
    }

    public function getSkill(): int
    {
        return $this->skill;
    }
}
