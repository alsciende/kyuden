<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Entity\LogicalCard;
use App\Repository\LogicalCardRepository;

/**
 * The cards of the deck by their code in the engine (`GET /api/cards`).
 */
readonly class CodesDeckFormat implements DeckFormatInterface
{
    public function __construct(
        private LogicalCardRepository $logicalCardRepository,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return 'codes';
    }

    #[\Override]
    public function toCodes(array $cards): array
    {
        $codes = array_map(
            fn (LogicalCard $card): string => $card->getId(),
            $this->logicalCardRepository->findBy(['id' => array_map(strval(...), array_keys($cards))]),
        );

        return [
            'cards' => array_intersect_key($cards, array_flip($codes)),
            'unknown' => array_values(array_diff(array_map(strval(...), array_keys($cards)), $codes)),
        ];
    }
}
