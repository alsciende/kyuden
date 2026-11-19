<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Clan;

/**
 * The games of a deck won and lost against a clan (the clan of the stronghold of the opponent).
 */
final readonly class DeckOpponentRecordView
{
    public function __construct(
        public Clan $clan,
        public int $won,
        public int $lost,
    ) {
    }
}
