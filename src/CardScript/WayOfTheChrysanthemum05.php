<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\HonorTransferredEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Way of the Chrysanthemum.
 */
final readonly class WayOfTheChrysanthemum05 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '05-way-of-the-chrysanthemum';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorTransferredEvent::class => 'onHonorTransferred'];
    }

    /**
     * After 1 or more honor is given to you from an honor bid.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorTransferred(HonorTransferredEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->fromHonorBid
            && $event->recipient === $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
