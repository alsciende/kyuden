<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\HonorLostEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Righteous Samurai.
 */
final readonly class RighteousSamurai34 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '34-righteous-samurai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorLostEvent::class => 'onHonorLost'];
    }

    /**
     * After an opponent's card or ring effect causes you to lose 1 or more honor: offered for every card effect (the engine does not know whose effect it applies).
     *
     * @return list<ReactionOffer>
     */
    public function onHonorLost(HonorLostEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $event->byCardEffect
            ? [new ReactionOffer($card)]
            : [];
    }
}
