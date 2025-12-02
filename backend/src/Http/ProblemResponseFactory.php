<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ProblemResponseFactory
{
    public function create(int $status, string $message, ?string $code = null, array $details = []): JsonResponse
    {
        $payload = [
            'status' => $status,
            'code' => $code ?? $this->defaultCode($status),
            'message' => $message,
            'details' => empty($details) ? null : $details,
        ];

        return new JsonResponse($payload, $status);
    }

    public function validationFailed(ConstraintViolationListInterface $violations, string $message = 'Validation failed', ?string $code = null): JsonResponse
    {
        $details = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $details[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->create(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $code ?? 'validation_error', $details);
    }

    private function defaultCode(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthorized',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_error',
            default => 'error',
        };
    }
}

