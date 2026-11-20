<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclaredEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Enlightened Warrior.
 */
final readonly class EnlightenedWarrior01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-enlightened-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclaredEvent::class => 'onConflictDeclared'];
    }

    /**
     * After an opponent selects a ring with fate on it.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclared(ConflictDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            && $event->ringFate > 0
            ? [new ReactionOffer($card)]
            : [];
    }
}
