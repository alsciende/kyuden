<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Player;

/**
 * A province would be broken: by the attacking player at the resolution of a conflict (`provinces_broken`, 3.2.5), or
 * by the player whose effect breaks it (BreakProvince). The interrupts "when this province is broken" come before it
 * is, the province still unbroken (its abilities can be triggered); cancelled ("would be broken… instead": Public
 * Forum), the province is not broken.
 */
final readonly class ProvinceBreakingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public ProvinceCard $province,
        /**
         * The player who controls it.
         */
        public Player $controller,
        /**
         * The player who would break it.
         */
        public Player $breaker,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'province_breaking';
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
            'card' => $this->province->getId(),
            'controller' => $this->controller->getId(),
            'breaker' => $this->breaker->getId(),
        ];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{card} would be broken';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['card' => $this->province];
    }
}
