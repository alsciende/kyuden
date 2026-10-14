<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\DuelType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The player resolving a card ability initiates a duel (D.1) between characters they control (`challengerIds`) and
 * characters controlled by an opponent (`challengedIds`). The players involved then bid (`BidHonor`).
 */
#[AppAction]
final readonly class InitiateDuel implements PlayerActionInterface
{
    /**
     * @param list<string> $challengerIds
     * @param list<string> $challengedIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Type of the duel.')]
        private DuelType $type,
        #[Description('Characters controlled by the player who initiates the duel.')]
        #[Assert\Count(min: 1)]
        private array $challengerIds,
        #[Description('Characters controlled by the challenged opponent.')]
        #[Assert\Count(min: 1)]
        private array $challengedIds,
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

    public function getType(): DuelType
    {
        return $this->type;
    }

    /**
     * @return list<string>
     */
    public function getChallengerIds(): array
    {
        return $this->challengerIds;
    }

    /**
     * @return list<string>
     */
    public function getChallengedIds(): array
    {
        return $this->challengedIds;
    }
}
