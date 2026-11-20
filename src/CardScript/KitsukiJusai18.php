<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\HonorDialsRevealedEvent;
use App\Message\MoveFate;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kitsuki Jusai.
 */
final readonly class KitsukiJusai18 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '18-kitsuki-jusai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorDialsRevealedEvent::class => 'onHonorDialsRevealed'];
    }

    /**
     * After honor dials are revealed, if your honor bid is equal to an opponent's.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorDialsRevealed(HonorDialsRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $opponent = $this->triggerConditions->getOpponent($you);

        return $event->revealedBy($you)
            && $event->revealedBy($opponent)
            && $this->triggerConditions->getHonorBid($you) === $this->triggerConditions->getHonorBid($opponent)
            ? [new ReactionOffer($card)]
            : [];
    }
}
