<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * How a deck of the user has fared: the games played with it (every game but the replays: the queue, the tournaments,
 * the house bot, the friendly games), those won and lost, the standard ones apart (`ranked_*`: neither friendly nor
 * against the house bot), how they ended and against which clans. A game ended without winner is played, neither won
 * nor lost.
 */
final readonly class DeckRecordView
{
    /**
     * @param list<DeckEndingRecordView>   $endings   by victory condition, in the order of the enum, those that happened only
     * @param list<DeckOpponentRecordView> $opponents by clan of the stronghold of the opponent, in the order of the enum, those faced only
     */
    public function __construct(
        /** the games ended */
        public int $played,
        public int $won,
        public int $lost,
        /** the games started that have not ended */
        public int $inProgress,
        public int $rankedWon,
        public int $rankedLost,
        /** the last game ended, null before the first one */
        public ?DeckLastGameView $lastGame,
        public array $endings,
        public array $opponents,
    ) {
    }
}
