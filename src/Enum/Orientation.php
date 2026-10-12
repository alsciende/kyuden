<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Physical orientation of a card on the board. Sideways and UpsideDown cards are face up.
 *
 * What an orientation means depends on the card type: a Sideways character is bowed,
 * an UpsideDown province is broken.
 */
enum Orientation: string
{
    case FaceDown = 'face_down';
    case FaceUp = 'face_up';
    case Sideways = 'sideways';
    case UpsideDown = 'upside_down';
}
