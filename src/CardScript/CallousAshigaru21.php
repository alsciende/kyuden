<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\ProvinceBrokenEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Callous Ashigaru.
 */
final readonly class CallousAshigaru21 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '21-callous-ashigaru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceBrokenEvent::class => 'onProvinceBroken'];
    }

    /**
     * After you break a province during a [conflict-military] conflict in which attached character is attacking.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceBroken(ProvinceBrokenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->breaker === $you
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->isAttacking($character)
            ? [new ReactionOffer($card)]
            : [];
    }
}
