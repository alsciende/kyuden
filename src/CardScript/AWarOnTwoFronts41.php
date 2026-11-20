<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Message\AddAttackedProvince;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * A War on Two Fronts.
 */
final readonly class AWarOnTwoFronts41 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '41-a-war-on-two-fronts';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddAttackedProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted'];
    }

    /**
     * After you declare a [conflict-military] conflict, if you are more honorable than your opponent.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->attacker === $you
            && $event->type === ConflictType::Military
            && $this->triggerConditions->isMoreHonorable($you, $event->defender)
            ? [new ReactionOffer($card)]
            : [];
    }
}
