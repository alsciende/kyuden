<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DuelWonEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kakita Blade.
 */
final readonly class KakitaBlade03 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '03-kakita-blade';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DuelWonEvent::class => 'onDuelWon'];
    }

    /**
     * After attached character wins a duel.
     *
     * @return list<ReactionOffer>
     */
    public function onDuelWon(DuelWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->player === $this->triggerConditions->getController($character)
            && $this->triggerConditions->isInvolvedInDuel($character)
            ? [new ReactionOffer($card)]
            : [];
    }
}
