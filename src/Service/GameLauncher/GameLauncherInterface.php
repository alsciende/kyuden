<?php

declare(strict_types=1);

namespace App\Service\GameLauncher;

use App\Entity\Candidate;
use App\Entity\Invitation;
use App\Entity\TournamentMatch;

interface GameLauncherInterface
{
    /**
     * @param array<Candidate> $candidates
     * @param Invitation|null  $invitation the invitation the candidates are matched by: the game is friendly, and the
     *                                     invitation is used
     * @param TournamentMatch|null $match the match of a tournament the candidates are matched by: the game is
     *                                    standard, and the game of the match
     */
    public function createGame(array $candidates, ?Invitation $invitation = null, ?TournamentMatch $match = null): void;
}
