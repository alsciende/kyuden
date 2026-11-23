<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\FirstPlayerTokenPassingTrigger;

/**
 * Way of the Unicorn.
 */
final readonly class WayOfTheUnicorn01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-way-of-the-unicorn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [FirstPlayerTokenPassingTrigger::class => 'onFirstPlayerTokenPassing'];
    }

    /**
     * When you would pass the first player token: keep it instead.
     *
     * @return list<InterruptOffer>
     */
    public function onFirstPlayerTokenPassing(FirstPlayerTokenPassingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
