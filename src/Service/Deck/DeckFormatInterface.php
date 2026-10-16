<?php

declare(strict_types=1);

namespace App\Service\Deck;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A format in which the API receives the cards of a deck: quantity by card id, the ids being those of a database of
 * cards. Each format converts them to the codes of the cards of the engine.
 */
#[AutoconfigureTag]
interface DeckFormatInterface
{
    /**
     * The name of the format in the API (`format` of the deck).
     */
    public function getName(): string;

    /**
     * @param array<string, int> $cards quantity by card id of the format
     *
     * @return array{cards: array<string, int>, unknown: list<string>} quantity by card code (ids naming the same card
     *                                                                  are added up), and the ids naming no card
     */
    public function toCodes(array $cards): array;
}
