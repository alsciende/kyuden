<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * Another tournament (not cancelled) begins less than 12 hours before or after the start chosen.
 */
class TournamentTooCloseException extends \RuntimeException
{
    public function __construct(
        private readonly \DateTimeImmutable $otherStartsAt,
    ) {
        parent::__construct('Another tournament begins less than 12 hours before or after: two tournaments stand at least 12 hours apart.');
    }

    /**
     * When the other tournament begins.
     */
    public function getOtherStartsAt(): \DateTimeImmutable
    {
        return $this->otherStartsAt;
    }
}
