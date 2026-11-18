<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\TournamentMatch;
use App\Enum\Trophy;
use App\Repository\TournamentMatchRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;

/**
 * Finish a game of a tournament, won or lost: by honor, by breaking the stronghold province, by a concession (of either
 * player) or by the abandonment of the opponent; not when the player left without conceding (their opponent ended the
 * game), nor in a game ended without a winner.
 */
readonly class TournamentGame implements GameTrophyInterface
{
    public function __construct(
        private TournamentMatchRepository $tournamentMatchRepository,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    #[\Override]
    public static function getTrophy(): Trophy
    {
        return Trophy::TournamentGame;
    }

    #[\Override]
    public function isEarnedBy(Player $player, Game $game, GameState $state): bool
    {
        return $state->winnerId !== null
            && ! $this->playerStateManager->getState($player)->isAbandoned()
            && $this->tournamentMatchRepository->findOneByGame($game) instanceof TournamentMatch;
    }
}
