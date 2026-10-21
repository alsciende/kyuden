<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

use function Symfony\Component\String\u;

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

    /**
     * The values of a payload break constraints of the Validator: 422 `validation_failed`, with its violations.
     */
    private function validationProblem(ConstraintViolationListInterface $violations): JsonResponse
    {
        $list = [];
        foreach ($violations as $violation) {
            $list[] = [
                'property_path' => u($violation->getPropertyPath())->snake()->toString(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'validation_failed', 'The payload has invalid values.', ['violations' => $list]);
    }
}
