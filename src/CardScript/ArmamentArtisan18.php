<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\PersonalHonor;
use App\Event\PersonalHonorChangedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Armament Artisan.
 */
final readonly class ArmamentArtisan18 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '18-armament-artisan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PersonalHonorChangedEvent::class => 'onPersonalHonorChanged'];
    }

    /**
     * After another character you control is honored.
     *
     * @return list<ReactionOffer>
     */
    public function onPersonalHonorChanged(PersonalHonorChangedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->card !== $card
            && $event->action === PersonalHonor::Honored
            && $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
