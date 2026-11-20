<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Message\FillProvince;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Shiba Pureheart.
 */
final readonly class ShibaPureheart34 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '34-shiba-pureheart';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted'];
    }

    /**
     * After an opponent declares their second conflict against you this phase.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->attacker !== $you
            && $this->triggerConditions->countDeclaredConflicts($event->attacker) === 2
            ? [new ReactionOffer($card)]
            : [];
    }
}
