<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\Orientation;
use App\Enum\StackType;

/**
 * The visibility rules of the cards, shared by the view of the game (GameViewFactory) and its log. They only depend on
 * the state, never on the game place:
 * - decks are hidden from every player, the hand from the opponents: the cards in them, ids included, are hidden;
 * - a face-down card is hidden from every player, except a face-down province from its owner: its id is known, not its
 *   code;
 * - everything else is public.
 *
 * The rules are written for a position (stack type, owner of the stack, orientation), so that the position a card is
 * about to take can be checked before it moves.
 */
final readonly class Visibility
{
    /**
     * Whether the viewer sees the cards of a stack (their ids).
     */
    public function isStackVisibleTo(StackType $type, Player $owner, Player $viewer): bool
    {
        return match ($type) {
            StackType::ConflictDrawDeck, StackType::DynastyDrawDeck => false,
            StackType::Hand => $owner === $viewer,
            default => true,
        };
    }

    /**
     * Whether the viewer knows a card (its code) when it is in that position.
     */
    public function isCardVisibleTo(PhysicalCard $card, StackType $type, Player $owner, Orientation $orientation, Player $viewer): bool
    {
        return $this->isStackVisibleTo($type, $owner, $viewer)
            && ($orientation !== Orientation::FaceDown || ($card instanceof ProvinceCard && $card->getPlayer() === $viewer));
    }

    public function isCardInStackVisibleTo(PhysicalCard $card, Stack $stack, Player $viewer): bool
    {
        return $this->isCardVisibleTo($card, $stack->getType(), $stack->getPlayer(), $card->getOrientation(), $viewer);
    }
}
