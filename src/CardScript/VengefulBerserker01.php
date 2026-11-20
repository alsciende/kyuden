<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardLeftPlayEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Vengeful Berserker.
 */
final readonly class VengefulBerserker01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-vengeful-berserker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeftPlayEvent::class => 'onCardLeftPlay'];
    }

    /**
     * After another character you control leaves play during a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCardLeftPlay(CardLeftPlayEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->character
            && $event->card !== $card
            && $event->controller === $you
            && $this->triggerConditions->isConflictInProgress($game)
            ? [new ReactionOffer($card)]
            : [];
    }
}
