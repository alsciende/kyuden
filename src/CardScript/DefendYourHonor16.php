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
use App\Trigger\EffectsInitiatingTrigger;

/**
 * Defend Your Honor.
 */
final readonly class DefendYourHonor16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-defend-your-honor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [EffectsInitiatingTrigger::class => 'onEffectsInitiating'];
    }

    /**
     * When the effects of an opponent's event would initiate during a conflict, initiate a military duel against a character your opponent controls of their choice – resolve the duel. If your character is the duel's winner, cancel those effects.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiatingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->event && $trigger->player !== $this->triggerConditions->getController($card) && $this->triggerConditions->isConflictInProgress($game)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, InitiateDuel::class];
    }
}
