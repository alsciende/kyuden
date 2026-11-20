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
 * Ardent Omoidasu.
 */
final readonly class ArdentOmoidasu27 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '27-ardent-omoidasu';
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
     * After an opponent's card ability or ring effect dishonors a character you control: offered for every character dishonored (the engine does not know whose effect it applies).
     *
     * @return list<ReactionOffer>
     */
    public function onPersonalHonorChanged(PersonalHonorChangedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->action === PersonalHonor::Dishonored
            && $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
