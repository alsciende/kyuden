<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How the web client draws the cards on the board for a user.
 */
enum CardDisplay: string
{
    /** a frame with the name and the values of the card */
    case Drawn = 'drawn';
    /** the image of the card, its back when face down */
    case Images = 'images';
}
