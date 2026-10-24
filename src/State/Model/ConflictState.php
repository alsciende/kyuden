<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\ConflictType;
use App\Enum\Element;

/**
 * The conflict being resolved, from its declaration until its participants return home. Cards are referenced by id.
 */
class ConflictState
{
    /**
     * Every attacked province: the province with the contested ring first, then the provinces the conflict is also at
     * (A War on Two Fronts).
     *
     * @var list<string>
     */
    public array $attackedProvinceIds;

    /**
     * Participating characters on the side of the attacking player.
     *
     * @var list<string>
     */
    public array $attackerIds = [];

    /**
     * Participating characters on the side of the defending player.
     *
     * @var list<string>
     */
    public array $defenderIds = [];

    /**
     * Characters evaded by covert: they cannot be declared as defenders.
     *
     * @var list<string>
     */
    public array $evadedIds = [];

    /**
     * Total skill of each side, declared by its player (3.2.3).
     */
    public ?int $attackerSkill = null;

    public ?int $defenderSkill = null;

    /**
     * Strength of each attacked province, by province id: the printed strength unless the defending player declares
     * another one.
     *
     * @var array<string, int>
     */
    public array $provinceStrengths = [];

    /**
     * Attacked provinces whose strength the defending player has declared: once each.
     *
     * @var list<string>
     */
    public array $declaredStrengthProvinceIds = [];

    /**
     * Set when the result is determined; null when no player won the conflict.
     */
    public ?string $winnerId = null;

    /**
     * @var list<string>
     */
    public array $brokenProvinceIds = [];

    public function __construct(
        public string $attackingPlayerId,
        public string $defendingPlayerId,
        public Element $ring,
        public ConflictType $type,
        /**
         * The province the conflict was declared against, where the contested ring is.
         */
        public string $provinceId,
    ) {
        $this->attackedProvinceIds = [$provinceId];
    }

    /**
     * Both skill totals are declared. Not prefixed with `is` or `has`, so that the serializer of the state does not
     * take it for a property; same for the other methods.
     */
    public function skillsDeclared(): bool
    {
        return $this->attackerSkill !== null && $this->defenderSkill !== null;
    }

    /**
     * The player of the conflict has declared the skill of their side.
     */
    public function skillDeclaredBy(string $playerId): bool
    {
        return match ($playerId) {
            $this->attackingPlayerId => $this->attackerSkill !== null,
            $this->defendingPlayerId => $this->defenderSkill !== null,
            default => false,
        };
    }

    /**
     * The defending player has declared the strength of every attacked province.
     */
    public function strengthsDeclared(): bool
    {
        return array_diff($this->attackedProvinceIds, $this->declaredStrengthProvinceIds) === [];
    }

    /**
     * 3.2.3: the side with the higher total skill wins, the attacking player on a tie; a total of at least 1 is needed
     * to win.
     */
    public function determineWinner(): void
    {
        $attackerSkill = $this->attackerSkill ?? throw new \LogicException('The attacking player has not declared their skill.');
        $defenderSkill = $this->defenderSkill ?? throw new \LogicException('The defending player has not declared their skill.');

        $this->winnerId = match (true) {
            $attackerSkill >= $defenderSkill && $attackerSkill > 0 => $this->attackingPlayerId,
            $defenderSkill > $attackerSkill => $this->defendingPlayerId,
            default => null,
        };
    }

    public function attackerWon(): bool
    {
        return $this->winnerId === $this->attackingPlayerId;
    }

    /**
     * By how much the attacking player won the conflict.
     */
    public function excessSkill(): int
    {
        return ($this->attackerSkill ?? 0) - ($this->defenderSkill ?? 0);
    }
}
