<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Orientation;
use App\Event\ConflictDeclaredEvent;
use App\Message\MoveConflict;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * The Crashing Wave.
 */
final readonly class TheCrashingWave28 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '28-the-crashing-wave';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveConflict::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclaredEvent::class => 'onConflictDeclared'];
    }

    /**
     * After an attack is declared against a province (you control) but before the province is revealed.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclared(ConflictDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->defender === $you
            && $event->province->getOrientation() === Orientation::FaceDown
            ? [new ReactionOffer($card)]
            : [];
    }
}
