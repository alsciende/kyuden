<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Trigger\CharacterDishonoringTrigger;
use App\Trigger\CharacterHonoringTrigger;

/**
 * Young Rumormonger.
 */
final readonly class YoungRumormonger01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-young-rumormonger';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            CharacterHonoringTrigger::class => 'onCharacterHonoring',
            CharacterDishonoringTrigger::class => 'onCharacterDishonoring',
        ];
    }

    /**
     * When a character would be honored, choose another character controlled by the same player.
     *
     * @return list<InterruptOffer>
     */
    public function onCharacterHonoring(CharacterHonoringTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $this->offer($trigger->controller, $card);
    }

    /**
     * When a character would be dishonored, choose another character controlled by the same player.
     *
     * @return list<InterruptOffer>
     */
    public function onCharacterDishonoring(CharacterDishonoringTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $this->offer($trigger->controller, $card);
    }

    /**
     * @return list<InterruptOffer>
     */
    private function offer(Player $controller, PhysicalCard $card): array
    {
        return count($controller->getCharactersInPlay()) >= 2
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
