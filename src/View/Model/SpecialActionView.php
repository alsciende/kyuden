<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A special action (AppAction::$special) and the active cards of the viewer that need it (SpecialActionsResolver): the
 * web client offers it apart when some do, and on demand otherwise.
 */
final readonly class SpecialActionView
{
    /**
     * @param string       $type    the type of the message
     * @param list<string> $cardIds the active cards of the viewer whose text needs it, none if no card does
     */
    public function __construct(
        public string $type,
        public array $cardIds,
    ) {
    }
}
