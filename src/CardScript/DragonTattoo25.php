<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Type;
use App\Event\CardPlayedEvent;
use App\Message\RemoveFromGame;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Dragon Tattoo.
 */
final readonly class DragonTattoo25 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '25-dragon-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardPlayedEvent::class => 'onCardPlayed'];
    }

    /**
     * After you play an event that chooses attached character as a target: offered for every event you play (the engine does not know the targets).
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->player === $you
            && $event->isOfType(Type::Event)
            ? [new ReactionOffer($card)]
            : [];
    }
}
