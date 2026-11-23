<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Element;

/**
 * A player would declare a conflict (DeclareConflict), against a province of the defending player, before it is
 * revealed (Know the Terrain). Nothing cancels it.
 */
final readonly class ConflictDeclaringTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Player $attacker,
        public Player $defender,
        public ProvinceCard $province,
        public Element $ring,
        public ConflictType $type,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'conflict_declaring';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'player' => $this->attacker->getId(),
            'defender' => $this->defender->getId(),
            'province' => $this->province->getId(),
            'ring' => $this->ring->value,
            'type' => $this->type->value,
        ];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{player} is about to declare a conflict against {province}';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->attacker, 'province' => $this->province];
    }
}
