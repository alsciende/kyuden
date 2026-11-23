<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\CardsDrawingTrigger;

/**
 * Subterfuge.
 */
final readonly class Subterfuge41 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '41-subterfuge';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardsDrawingTrigger::class => 'onCardsDrawing'];
    }

    /**
     * When an opponent would draw 1 or more cards outside the draw phase, if you are less honorable than that opponent: discard up to 3 of those cards instead.
     *
     * @return list<InterruptOffer>
     */
    public function onCardsDrawing(CardsDrawingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $player = $this->triggerConditions->getController($card);

        return $trigger->player !== $player && $this->triggerConditions->isMoreHonorable($trigger->player, $player)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
