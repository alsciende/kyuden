<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * A deck sent to the API cannot be imported: unknown format, cards unknown to the format, or a deck breaking the
 * deckbuilding rules of the Stronghold format.
 */
class InvalidDeckException extends \RuntimeException
{
    /**
     * @param list<array{property_path: string, message: string}> $violations
     */
    public function __construct(
        private readonly array $violations,
    ) {
        parent::__construct('The deck cannot be imported.');
    }

    /**
     * @return list<array{property_path: string, message: string}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
