<?php

declare(strict_types=1);

namespace App\Exception\Api;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The payload of a message does not match its structure: missing, unknown or reserved field, wrong type.
 */
class InvalidPayloadException extends BadRequestHttpException
{
    /**
     * @param list<array{property_path: string, message: string}> $violations
     */
    public function __construct(
        string $type,
        private readonly array $violations,
    ) {
        parent::__construct("Invalid payload for {$type}.");
    }

    /**
     * @return list<array{property_path: string, message: string}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
