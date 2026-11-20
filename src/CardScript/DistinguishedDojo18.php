<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DuelWonEvent;
use App\Message\PlaceHonorTokens;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Distinguished Dōjō.
 */
final readonly class DistinguishedDojo18 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '18-distinguished-dojo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceHonorTokens::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DuelWonEvent::class => 'onDuelWon'];
    }

    /**
     * After you win a duel.
     *
     * @return list<ReactionOffer>
     */
    public function onDuelWon(DuelWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
