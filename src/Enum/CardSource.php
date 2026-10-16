<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A database of cards outside of the engine, whose ids are linked to the codes of the cards (App\Entity\ExternalCard).
 */
enum CardSource: string
{
    case EmeraldDb = 'emeralddb';
}
