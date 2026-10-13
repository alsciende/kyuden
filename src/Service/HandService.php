<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Type;
use App\Exception\Rules\NotInHandException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;

/**
 * Rules about playing a card from hand.
 */
readonly class HandService
{
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
}
