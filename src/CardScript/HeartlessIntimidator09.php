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
 * Heartless Intimidator.
 */
final readonly class HeartlessIntimidator09 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '09-heartless-intimidator';
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
     * After an opponent loses 1 or more honor.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorLost(HonorLostEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
