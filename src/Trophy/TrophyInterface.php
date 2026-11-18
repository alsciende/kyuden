<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Enum\Trophy;

/**
 * The rule of a trophy, one class per trophy, named after its case of Enum/Trophy (which identifies it, gives its
 * reward and its place in the catalogue). A rule says when it is read: at the end of a game (GameTrophyInterface) or
 * at the end of a tournament (TournamentTrophyInterface). A stateless service, which reads only what is stored, so
 * that what was played before earns the trophy too (`app:trophies:award`).
 */
interface TrophyInterface
{
    /**
     * The trophy this rule awards.
     */
    public static function getTrophy(): Trophy;
}
