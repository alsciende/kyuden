<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\AbilityTriggeredEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kirei-ko.
 */
final readonly class KireiKo16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-kirei-ko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [AbilityTriggeredEvent::class => 'onAbilityTriggered'];
    }

    /**
     * After a character your opponent controls triggers an ability.
     *
     * @return list<ReactionOffer>
     */
    public function onAbilityTriggered(AbilityTriggeredEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->character instanceof PhysicalCard
            && ! $this->triggerConditions->isControlledBy($event->character, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
