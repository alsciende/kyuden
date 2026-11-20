<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictResolvedEvent;
use App\Message\DeclareNextConflict;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Breakthrough.
 */
final readonly class Breakthrough01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-breakthrough';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DeclareNextConflict::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictResolvedEvent::class => 'onConflictResolved'];
    }

    /**
     * After the resolution of your first conflict this phase as the attacking player, if that conflict broke a province.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictResolved(ConflictResolvedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->attacker === $you
            && $event->brokenProvinceIds !== []
            && $this->triggerConditions->countDeclaredConflicts($you) === 1
            ? [new ReactionOffer($card)]
            : [];
    }
}
