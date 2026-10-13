<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Enum\ConflictType;
use App\Enum\Element;

/**
 * The active player declares a conflict (3.2): the contested ring, taken from the unclaimed ring pool, the type of the
 * conflict, the attacked province of an opponent and the ready characters declared as attackers. `evadedIds` are the
 * characters of the defending player chosen with covert: the engine does not check the covert keyword.
 */
#[AppAction]
final readonly class DeclareConflict implements PlayerActionInterface
{
    /**
     * @param list<string> $attackerIds
     * @param list<string> $evadedIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        private Element $ring,
        private ConflictType $type,
        private string $provinceId,
        private array $attackerIds,
        private array $evadedIds = [],
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

    public function getRing(): Element
    {
        return $this->ring;
    }

    public function getType(): ConflictType
    {
        return $this->type;
    }

    public function getProvinceId(): string
    {
        return $this->provinceId;
    }

    /**
     * @return list<string>
     */
    public function getAttackerIds(): array
    {
        return $this->attackerIds;
    }

    /**
     * @return list<string>
     */
    public function getEvadedIds(): array
    {
        return $this->evadedIds;
    }
}
