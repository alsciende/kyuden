<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * An entry of the log of a game, as a reader sees it: its template and its params by placeholder name (see
 * App\GameLog\LogParam), without what the reader does not know: a hidden card has no id nor code (no code when only
 * its id is known), a hidden value has no value (`hidden`).
 */
final readonly class LogEntryView
{
    /**
     * @param array<string, array<string, mixed>> $params
     */
    public function __construct(
        public string $template,
        public array $params,
    ) {
    }
}
