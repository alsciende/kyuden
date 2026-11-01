<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * A URL given to import a deck names no deck: not the URL of a deck of a known database of decks, or a deck the
 * database does not have (or does not show).
 */
class InvalidDeckUrlException extends \RuntimeException
{
}
