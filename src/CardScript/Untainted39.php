<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictWonEvent;
use App\Message\DiscardStatusToken;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Untainted.
 */
final readonly class Untainted39 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '39-untainted';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardStatusToken::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictWonEvent::class => 'onConflictWon'];
    }

    /**
     * After you win a conflict at attached province.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $province = $this->triggerConditions->getProvinceOf($card);

        return $this->triggerConditions->hasWonConflict($you)
            && $province instanceof PhysicalCard
            && $this->triggerConditions->isAttacked($province)
            ? [new ReactionOffer($card)]
            : [];
    }
}
