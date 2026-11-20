<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Clan;
use App\Enum\Type;
use App\Event\CardPlayedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Withstand the Darkness.
 */
final readonly class WithstandTheDarkness23 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '23-withstand-the-darkness';
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
     * After an opponent plays an event that chooses a [clan-crab] character you control as a target: offered for every event of an opponent while you control a [clan-crab] character (the engine does not know the targets).
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            && $event->isOfType(Type::Event)
            && array_any($you->getCharactersInPlay(), fn (PhysicalCard $character): bool => $character->getLogicalCard()->getClan() === Clan::Crab)
            ? [new ReactionOffer($card)]
            : [];
    }
}
