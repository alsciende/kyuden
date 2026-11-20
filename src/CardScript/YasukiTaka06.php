<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Clan;
use App\Enum\Phase;
use App\Event\CardLeftPlayEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Yasuki Taka.
 */
final readonly class YasukiTaka06 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '06-yasuki-taka';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeftPlayEvent::class => 'onCardLeftPlay'];
    }

    /**
     * After a [clan-crab] character leaves play during the conflict phase.
     *
     * @return list<ReactionOffer>
     */
    public function onCardLeftPlay(CardLeftPlayEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->character
            && $event->card->getLogicalCard()->getClan() === Clan::Crab
            && $this->triggerConditions->isPhase($game, Phase::Conflict)
            ? [new ReactionOffer($card)]
            : [];
    }
}
