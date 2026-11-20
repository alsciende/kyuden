<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Enum\Type;
use App\Service\CardScript\CardScriptRegistry;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * The abilities of a conflict or dynasty card are used in play (a card in play or attached; of the faceup cards in a
 * province, a holding is in play). Out of play, only where the text of the card says: in the stacks its script names
 * (AbstractCardScript::getAbilityStacksOutOfPlay(): Vengeful Oathkeeper in the hand, Champions of Yomi in the dynasty
 * discard pile, Sneaky Shinjo in a province), so that a character in a province or a card in a hand triggers nothing
 * otherwise.
 */
#[AsGuardListener(workflow: 'conflict_card', transition: 'trigger_ability')]
#[AsGuardListener(workflow: 'dynasty_card', transition: 'trigger_ability')]
readonly class TriggerAbilityGuardListener
{
    private const array IN_PLAY = [
        ConflictCard::STATE_IN_PLAY,
        ConflictCard::STATE_ATTACHED,
        DynastyCard::STATE_IN_PLAY,
        DynastyCard::STATE_ATTACHED,
    ];

    public function __construct(
        private CardScriptRegistry $cardScriptRegistry,
    ) {
    }

    /**
     * @param GuardEvent<PhysicalCard> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $card = $event->getSubject();
        if ($this->isInPlay($card)) {
            return;
        }

        $code = $card->getLogicalCard()->getId();
        if (! in_array($card->getStack()->getType(), $this->cardScriptRegistry->getAbilityStacksOutOfPlay($code), true)) {
            $event->setBlocked(true, 'The abilities of this card cannot be used where it is.');
        }
    }

    private function isInPlay(PhysicalCard $card): bool
    {
        $place = $card->getCurrentPlace();

        return in_array($place, self::IN_PLAY, true)
            || ($place === DynastyCard::STATE_PROVINCE_FACEUP && $card->getLogicalCard()->getType() === Type::Holding);
    }
}
