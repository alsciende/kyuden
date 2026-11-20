<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\StackType;
use App\Enum\ConflictType;
use App\Event\ConflictWonEvent;
use App\Message\PutCharacterIntoPlay;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Vengeful Oathkeeper.
 */
final readonly class VengefulOathkeeper01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-vengeful-oathkeeper';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }

    #[\Override]
    public function getAbilityStacksOutOfPlay(): array
    {
        return [StackType::Hand];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictWonEvent::class => 'onConflictWon'];
    }

    /**
     * After you lose a [conflict-military] conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $this->triggerConditions->hasLostConflict($you)
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            ? [new ReactionOffer($card)]
            : [];
    }
}
