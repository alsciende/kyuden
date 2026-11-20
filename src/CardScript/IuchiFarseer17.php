<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardEnteredPlayEvent;
use App\Model\ReactionOffer;

/**
 * Iuchi Farseer.
 */
final readonly class IuchiFarseer17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-iuchi-farseer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardEnteredPlayEvent::class => 'onCardEnteredPlay'];
    }

    /**
     * After this character enters play.
     *
     * @return list<ReactionOffer>
     */
    public function onCardEnteredPlay(CardEnteredPlayEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            ? [new ReactionOffer($card)]
            : [];
    }
}
