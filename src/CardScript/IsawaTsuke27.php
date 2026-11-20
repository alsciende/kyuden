<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\PersonalHonorChangedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Isawa Tsuke.
 */
final readonly class IsawaTsuke27 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '27-isawa-tsuke';
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
     * After you honor or dishonor a character by the effect of the [element-fire] ring: while the winner of a conflict for the fire ring resolves its effect.
     *
     * @return list<ReactionOffer>
     */
    public function onPersonalHonorChanged(PersonalHonorChangedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $game->getCurrentPlace() === 'resolve_ring_effects'
            && $event->action !== null
            && $this->triggerConditions->isConflictOfElement($game, Element::Fire)
            && $this->triggerConditions->hasWonConflict($you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
