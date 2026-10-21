<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Deck;
use App\View\Model\DeckView;

/**
 * Builds the view of a deck a user can use (HTTP API and MCP tools).
 */
final readonly class DeckViewFactory
{
    public function create(Deck $deck): DeckView
    {
        return new DeckView($deck->getId(), $deck->getName(), $deck->getOwner()?->getId(), $deck->getCards());
    }
}
