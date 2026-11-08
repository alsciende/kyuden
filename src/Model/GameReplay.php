<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Game;
use App\Entity\Player;

/**
 * The replay of a game that has ended, followed by one of its players: a copy of the game under another id, rebuilt
 * from the events of the original (Service/Replay/GameReplayer). Its version is the number of events replayed.
 */
final readonly class GameReplay
{
    public function __construct(
        public Game $original,
        /**
         * The player of the original game who follows the replay.
         */
        public Player $viewer,
        public ReplayIdTranslator $translator,
    ) {
    }

    public function getReplayGameId(): string
    {
        return $this->translator->replayGameId;
    }

    /**
     * The id of the viewer in the replay.
     */
    public function getReplayViewerId(): string
    {
        return $this->translator->translateId($this->viewer->getId());
    }
}
