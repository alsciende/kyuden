<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Message\InitiateDuel;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\ConflictLosingTrigger;

/**
 * Kakita Toshimoko.
 */
final readonly class KakitaToshimoko16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-kakita-toshimoko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictLosingTrigger::class => 'onConflictLosing'];
    }

    /**
     * When this character would lose a conflict (cancelled once its duel is won: the conflict has no winner).
     *
     * @return list<InterruptOffer>
     */
    public function onConflictLosing(ConflictLosingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->loser === $this->triggerConditions->getController($card) && $this->triggerConditions->isParticipating($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class, CancelImminentTrigger::class];
    }
}
