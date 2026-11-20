<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardEnteredPlayEvent;
use App\Model\ReactionOffer;

/**
 * Elegant Tessen.
 */
final readonly class ElegantTessen25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-elegant-tessen';
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
     * After this attachment enters play, if it is on a character with printed cost 2 or less.
     *
     * @return list<ReactionOffer>
     */
    public function onCardEnteredPlay(CardEnteredPlayEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $event->card === $card
            && $character instanceof PhysicalCard
            && ($character->getLogicalCard()->getCost() ?? 0) <= 2
            ? [new ReactionOffer($card)]
            : [];
    }
}
