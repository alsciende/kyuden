<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The pattern of the nobori of a player, repeated over it, in the ink of the patterns on its paper: none (the paper
 * alone), which every player has from the start, the solid ink (plain), or one of the traditional patterns, earned.
 * The web client draws each one from a tile of its own (`front/src/assets/patterns/<value>.svg`).
 */
enum BannerPattern: string
{
    case None = 'none';
    case Plain = 'plain';
    /** vertical stripes */
    case Tatejima = 'tatejima';
    /** broad bands */
    case Dandara = 'dandara';
    /** checks */
    case Ichimatsu = 'ichimatsu';
    /** lattice */
    case Koshi = 'koshi';
    /** fine dots */
    case Samekomon = 'samekomon';
    /** scales */
    case Uroko = 'uroko';
    /** waves */
    case Nami = 'nami';
    /** tortoise shell */
    case Kikko = 'kikko';
    /** hemp leaf */
    case Asanoha = 'asanoha';
    /** waves of the sea */
    case Seigaiha = 'seigaiha';
    /** arrow feathers */
    case Yagasuri = 'yagasuri';

    /**
     * Every player has it, earned or not.
     */
    public function isGivenToAll(): bool
    {
        return $this === self::None;
    }

    /**
     * Drawn from a tile (none and plain need none).
     */
    public function hasTile(): bool
    {
        return $this !== self::None && $this !== self::Plain;
    }
}
