<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The trophies a player earns by reaching goals (TROPHIES.md), in the order of the catalogue. A trophy may give a mon
 * or a pattern for the nobori. The rule of each is a class of `src/Trophy` named after its case: a GameTrophyInterface
 * (read at the end of a game) or a TournamentTrophyInterface (read at the end of a tournament). Their names and
 * goals are written by the web client (`front/src/data/trophies.ts`).
 */
enum Trophy: string
{
    /** Finish a game against the house bot (not by a concession or an abandonment). */
    case HouseBotGame = 'house_bot_game';

    /** Win a standard game with a stronghold of the clan (not by a concession or an abandonment). */
    case ClanCrab = 'clan_crab';
    case ClanCrane = 'clan_crane';
    case ClanDragon = 'clan_dragon';
    case ClanLion = 'clan_lion';
    case ClanPhoenix = 'clan_phoenix';
    case ClanScorpion = 'clan_scorpion';
    case ClanUnicorn = 'clan_unicorn';

    /** Finish a game of a tournament, won or lost (a concession of either player included), not left without conceding. */
    case TournamentGame = 'tournament_game';

    /** Finish first in a tournament, whatever the size of its bracket. */
    case TournamentWin = 'tournament_win';

    /**
     * The trophy of a Great Clan: null for the neutral cards.
     */
    public static function ofClan(Clan $clan): ?self
    {
        return $clan === Clan::Neutral ? null : self::from('clan_'.$clan->value);
    }

    /**
     * What it gives for the nobori (every trophy gives something for now: a trophy without a reward makes it nullable).
     */
    public function getReward(): Mon|BannerPattern
    {
        return match ($this) {
            self::HouseBotGame => BannerPattern::Plain,
            self::ClanCrab => Mon::Crab,
            self::ClanCrane => Mon::Crane,
            self::ClanDragon => Mon::Dragon,
            self::ClanLion => Mon::Lion,
            self::ClanPhoenix => Mon::Phoenix,
            self::ClanScorpion => Mon::Scorpion,
            self::ClanUnicorn => Mon::Unicorn,
            self::TournamentGame => BannerPattern::Tatejima,
            self::TournamentWin => BannerPattern::Dandara,
        };
    }
}
