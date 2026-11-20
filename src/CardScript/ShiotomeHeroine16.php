<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\HonorGainedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Shiotome Heroine.
 */
final readonly class ShiotomeHeroine16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-shiotome-heroine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorGainedEvent::class => 'onHonorGained'];
    }

    /**
     * After an opponent gains 1 or more honor through a card effect.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorGained(HonorGainedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            && $event->byCardEffect
            ? [new ReactionOffer($card)]
            : [];
    }
}
