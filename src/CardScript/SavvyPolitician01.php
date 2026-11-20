<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\PersonalHonor;
use App\Event\PersonalHonorChangedEvent;
use App\Model\ReactionOffer;

/**
 * Savvy Politician.
 */
final readonly class SavvyPolitician01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-savvy-politician';
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
     * After this character is honored.
     *
     * @return list<ReactionOffer>
     */
    public function onPersonalHonorChanged(PersonalHonorChangedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            && $event->action === PersonalHonor::Honored
            ? [new ReactionOffer($card)]
            : [];
    }
}
