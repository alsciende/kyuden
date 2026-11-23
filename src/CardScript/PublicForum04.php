<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Message\PlaceHonorTokens;
use App\Model\InterruptOffer;
use App\State\Manager\CardStateManager;
use App\Trigger\ProvinceBreaking;

/**
 * Public Forum.
 */
final readonly class PublicForum04 extends AbstractCardScript
{
    public function __construct(
        private CardStateManager $cardStateManager,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '04-public-forum';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, PlaceHonorTokens::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceBreaking::class => 'onProvinceBreaking'];
    }

    /**
     * When this province would be broken, if there are no honor tokens on it.
     *
     * @return list<InterruptOffer>
     */
    public function onProvinceBreaking(ProvinceBreaking $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->province === $card && $this->cardStateManager->getState($card)->getHonorTokens() === 0
            ? [new InterruptOffer($card, would: true)]
            : [];
    }
}
