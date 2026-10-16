<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * An action sent through the API (HTTP or MCP) is rejected: the problem as the client reads it (RFC 9457).
 */
class ApiProblemException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $extensions additional members of the problem (e.g. `violations`)
     */
    public function __construct(
        private readonly int $status,
        private readonly string $type,
        string $detail,
        private readonly array $extensions = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($detail, previous: $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'status' => $this->status,
            'detail' => $this->getMessage(),
            ...$this->extensions,
        ];
    }
}
