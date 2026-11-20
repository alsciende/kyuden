<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Event\PhaseBeganEvent;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Hiruma Kogoe.
 */
final readonly class HirumaKogoe11 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '11-hiruma-kogoe';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardOnDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * After the draw phase begins, if you have lower honor than an opponent.
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->phase === Phase::Draw
            && $this->triggerConditions->isMoreHonorable($this->triggerConditions->getOpponent($you), $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
