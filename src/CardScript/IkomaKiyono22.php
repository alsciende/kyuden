<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\GloryCountBeginningTrigger;

/**
 * Ikoma Kiyono.
 */
final readonly class IkomaKiyono22 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '22-ikoma-kiyono';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [GloryCountBeginningTrigger::class => 'onGloryCountBeginning'];
    }

    /**
     * When a glory count would begin, if you are more honorable than your opponent: ready this character (bowed).
     *
     * @return list<InterruptOffer>
     */
    public function onGloryCountBeginning(GloryCountBeginningTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $player = $this->triggerConditions->getController($card);

        return $this->triggerConditions->isBowed($card) && $this->triggerConditions->isMoreHonorable($player, $this->triggerConditions->getOpponent($player))
            ? [new InterruptOffer($card, would: true)]
            : [];
    }
}
