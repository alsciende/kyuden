<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Enum\StackType;
use App\Enum\Type;
use App\Exception\Rules\NoEventInHandException;
use App\Exception\Rules\NotInHandException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongAbilityTimingException;
use App\Exception\Rules\WrongCardTypeException;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Rules about playing a card from hand.
 */
readonly class HandService
{
    public function __construct(
        private PlayerStateManager $playerStateManager,
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    /**
     * A card of that type in the hand of the player.
     */
    public function assertPlayable(PhysicalCard $card, Player $player, Type $type): void
    {
        if ($card->getPlayer() !== $player) {
            throw new NotOwnedCardException($card, $player);
        }

        if ($card->getLogicalCard()->getType() !== $type) {
            throw new WrongCardTypeException($card, $type);
        }

        if ($card->getCurrentPlace() !== ConflictCard::STATE_HAND) {
            throw new NotInHandException($card);
        }
    }

    /**
     * The player has in hand an event with an ability of that timing.
     *
     * @throws NoEventInHandException
     */
    public function assertEventInHand(Player $player, AbilityTiming $timing): void
    {
        // through the stacks, which the reading API loads with the game
        foreach ($player->getStacks(StackType::Hand) as $hand) {
            foreach ($hand->getCards() as $card) {
                $logicalCard = $card->getLogicalCard();
                if ($logicalCard->getType() === Type::Event && $logicalCard->hasAbilityTiming($timing)) {
                    return;
                }
            }
        }

        throw new NoEventInHandException($timing);
    }

    /**
     * The player pays the cost of an event in their hand with an ability of that timing; the event goes to the discard
     * pile, and its effects are resolved with other actions.
     */
    public function playEvent(PhysicalCard $card, Player $player, int $cardCost, AbilityTiming $timing): void
    {
        $this->assertPlayable($card, $player, Type::Event);
        if (! $card->getLogicalCard()->hasAbilityTiming($timing)) {
            throw new WrongAbilityTimingException($card, $timing);
        }

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($cardCost),
        );
        $this->conflictCardStateMachine->apply($card, 'discard_card');
    }
}
