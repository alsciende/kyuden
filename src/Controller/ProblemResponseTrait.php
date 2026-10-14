<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Errors of the API as problem details (RFC 9457).
 */
trait ProblemResponseTrait
{
    private function problem(int $status, string $type, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type' => $type,
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
