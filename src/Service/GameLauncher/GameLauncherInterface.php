<?php

declare(strict_types=1);

namespace App\Service\GameLauncher;

use App\Entity\Candidate;
use App\Entity\Invitation;

interface GameLauncherInterface
{
    /**
     * @param array<Candidate> $candidates
     * @param Invitation|null  $invitation the invitation the candidates are matched by: the game is friendly, and the
     *                                     invitation is used
     */
    public function createGame(array $candidates, ?Invitation $invitation = null): void;
}
