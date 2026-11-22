<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The stages of an interrupt window, in order (glossary, Triggering Condition): the interrupts to what "would" occur
 * (the cancels, the replacements), then the forced interrupts, then the other interrupts.
 */
enum InterruptStage: string
{
    case Would = 'would';
    case Forced = 'forced';
    case Standard = 'standard';
}
