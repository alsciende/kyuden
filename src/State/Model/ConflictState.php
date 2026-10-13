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
}
