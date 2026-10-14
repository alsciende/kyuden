<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Enum\DuelType;

/**
 * The player resolving a card ability initiates a duel (D.1) between characters they control (`challengerIds`) and
 * characters controlled by an opponent (`challengedIds`). The players involved then bid (BidHonor).
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
        private DuelType $type,
        private array $challengerIds,
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
