<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Enum\CardSource;
use App\Repository\ExternalCardRepository;

/**
 * The `cards` of a deck of EmeraldDB (https://www.emeralddb.org): quantity by EmeraldDB card id, converted through
 * the external cards of the source (App\Entity\ExternalCard). The cards released after the engine's packs are unknown.
 */
readonly class EmeraldDbDeckFormat implements DeckFormatInterface
{
    public function __construct(
        private ExternalCardRepository $externalCardRepository,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return CardSource::EmeraldDb->value;
    }

    #[\Override]
    public function toCodes(array $cards): array
    {
        $codes = $this->externalCardRepository->findCardCodes(CardSource::EmeraldDb, array_map(strval(...), array_keys($cards)));

        $converted = [];
        $unknown = [];
        foreach ($cards as $externalId => $quantity) {
            $code = $codes[(string) $externalId] ?? null;
            if ($code === null) {
                $unknown[] = (string) $externalId;
                continue;
            }
            $converted[$code] = ($converted[$code] ?? 0) + $quantity;
        }

        return ['cards' => $converted, 'unknown' => $unknown];
    }
}
