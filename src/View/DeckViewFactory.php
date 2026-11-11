<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Deck;
use App\Repository\LogicalCardRepository;
use App\View\Model\DeckView;

/**
 * Builds the view of a deck a user can use (HTTP API and MCP tools), with its stronghold and the clan of it.
 */
final readonly class DeckViewFactory
{
    public function __construct(
        private LogicalCardRepository $logicalCardRepository,
    ) {
    }

    public function create(Deck $deck): DeckView
    {
        return $this->createAll([$deck])[0];
    }

    /**
     * The views of some decks, their strongholds read in a single query.
     *
     * @param list<Deck> $decks
     *
     * @return list<DeckView>
     */
    public function createAll(array $decks): array
    {
        $codes = [];
        foreach ($decks as $deck) {
            foreach (array_keys($deck->getCards()) as $code) {
                $codes[(string) $code] = true;
            }
        }
        $strongholds = $this->logicalCardRepository->findStrongholdsAmong(array_map(strval(...), array_keys($codes)));

        return array_map(static function (Deck $deck) use ($strongholds): DeckView {
            $stronghold = null;
            foreach (array_keys($deck->getCards()) as $code) {
                $stronghold ??= $strongholds[(string) $code] ?? null;
            }

            return new DeckView(
                $deck->getId(),
                $deck->getName(),
                $deck->getOwner()?->getId(),
                $deck->getCards(),
                $stronghold?->getId(),
                $stronghold?->getClan(),
            );
        }, $decks);
    }
}
