<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Errors of the API as problem details (RFC 9457).
 */
trait ProblemResponseTrait
{
    /**
     * @param array<string, mixed> $extensions additional members of the problem
     */
    private function problem(int $status, string $type, string $detail, array $extensions = []): JsonResponse
    {
        return new JsonResponse([
            'type' => $type,
            'status' => $status,
            'detail' => $detail,
            ...$extensions,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
