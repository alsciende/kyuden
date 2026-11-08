<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Puts dynasty cards of the provinces of a player back into their provinces face down, each into the province given,
 * so that no other player can tell where each card went (Governor's Spy: once `look_at_cards` on the provinces). The
 * face-up cards are first turned face down where they are; then every card is picked up and put back with a new id,
 * as after a shuffle. The log tells the other players that the provinces were redistributed, not where each card went.
 * A province left without a card is refilled once the effect is resolved.
 *
 * The cards can belong to any player: the engine does not apply card effects (nor "each province ends up with at least
 * one card").
 */
#[AppAction(special: true)]
final readonly class RedistributeProvinceCards implements PlayerActionInterface
{
    /**
     * @param list<string> $cardIds
     * @param list<string> $provinceIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player whose provinces they are.')]
        private string $targetPlayerId,
        #[Description('Dynasty cards in regular provinces of the target, face up or face down.')]
        #[Assert\Count(min: 1)]
        #[Assert\Unique]
        private array $cardIds,
        #[Description('Regular province of the target each card goes into, in the order of `cardIds`.')]
        #[Assert\Count(min: 1)]
        private array $provinceIds,
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

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    /**
     * @return list<string>
     */
    public function getCardIds(): array
    {
        return $this->cardIds;
    }

    /**
     * @return list<string>
     */
    public function getProvinceIds(): array
    {
        return $this->provinceIds;
    }
}
