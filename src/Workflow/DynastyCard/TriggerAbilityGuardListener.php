<?php

declare(strict_types=1);

namespace App\Workflow\DynastyCard;

use App\Entity\CardTypes\DynastyCard;
use App\Enum\Type;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Of the faceup cards in a province, only holdings are in play: the abilities of a character can only be used once it
 * has been played.
 */
#[AsGuardListener(workflow: 'dynasty_card', transition: 'trigger_ability')]
readonly class TriggerAbilityGuardListener
{
    /**
     * @param GuardEvent<DynastyCard> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $card = $event->getSubject();

        if ($card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEUP && $card->getLogicalCard()->getType() !== Type::Holding) {
            $event->setBlocked(true, 'Only a holding is in play in a province.');
        }
    }
}
