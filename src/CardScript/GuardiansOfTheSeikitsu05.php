<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceRevealedEvent;
use App\Model\ReactionOffer;

/**
 * Guardians of the Seikitsu.
 */
final readonly class GuardiansOfTheSeikitsu05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-guardians-of-the-seikitsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceRevealedEvent::class => 'onProvinceRevealed'];
    }

    /**
     * After this province is revealed.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceRevealed(ProvinceRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->province->getId() === $card->getId()
            ? [new ReactionOffer($card)]
            : [];
    }
}
