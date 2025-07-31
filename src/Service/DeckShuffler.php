<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use Random\Randomizer;

readonly class DeckShuffler
{
    /**
     * Edit the position of all cards. Return the sorted array.
     *
     * @param list<PhysicalCard> $cards
     *
     * @return list<PhysicalCard>
     */
    public function shuffleCards(array $cards): array
    {
        $randomizer = new Randomizer();
        /** @var list<PhysicalCard> $shuffleArray */
        $shuffleArray = $randomizer->shuffleArray($cards);
        foreach ($shuffleArray as $index => $card) {
            $card->setPosition($index);
        }

        return $shuffleArray;
    }
}
