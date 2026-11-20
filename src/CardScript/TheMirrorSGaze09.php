<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Type;
use App\Event\CardPlayedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * The Mirror's Gaze.
 */
final readonly class TheMirrorSGaze09 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '09-the-mirror-s-gaze';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardPlayedEvent::class => 'onCardPlayed'];
    }

    /**
     * After the effects of an opponent's event resolve: offered once it is played, its player triggers it once the effects have resolved (the engine does not know when).
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            && $event->isOfType(Type::Event)
            ? [new ReactionOffer($card)]
            : [];
    }
}
