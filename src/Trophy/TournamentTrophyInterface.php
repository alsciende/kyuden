<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The rule of a trophy earned in a tournament: read by Service/Trophy/TournamentTrophies once a tournament has ended,
 * for each of its players.
 */
#[AutoconfigureTag]
interface TournamentTrophyInterface extends TrophyInterface
{
    /**
     * Whether the player earns the trophy in the tournament, which has ended.
     */
    public function isEarnedBy(TournamentEntry $entry, Tournament $tournament): bool;
}
