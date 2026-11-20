<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictPassedEvent;
use App\Message\LookAtCards;
use App\Message\PutCardIntoProvince;
use App\Message\ShuffleDynastyDeck;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Our Foe Does Not Wait.
 */
final readonly class OurFoeDoesNotWait29 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '29-our-foe-does-not-wait';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardIntoProvince::class, ShuffleDynastyDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictPassedEvent::class => 'onConflictPassed'];
    }

    /**
     * After you pass on declaring a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictPassed(ConflictPassedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
