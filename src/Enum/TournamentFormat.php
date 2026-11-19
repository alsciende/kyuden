<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How the players of a tournament are paired, round after round: single elimination only for now.
 */
enum TournamentFormat: string
{
    case SingleElimination = 'single_elimination';
}
