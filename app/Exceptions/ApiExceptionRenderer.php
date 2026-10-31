<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Every API error leaves in the same shape:
 * {"error": {"code": "...", "message": "...", "details": {...}|null}}
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        [$status, $code, $message, $details] = match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', $e->getMessage(), $e->errors()],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.', null],
            $e instanceof ApiException => [$e->status(), $e->errorCode(), $e->getMessage(), $e->details()],
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => [500, 'server_error', config('app.debug') ? $e->getMessage() : 'Something went wrong.', null],
        };

        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message, 'details' => $details]],
            $status,
            // Keeps Retry-After and friends on 429/405 responses.
            $e instanceof HttpExceptionInterface ? $e->getHeaders() : [],
        );
    }

    /** @return array{int, string, string, null} */
    private function fromHttpException(HttpExceptionInterface $e): array
    {
        $status = $e->getStatusCode();

        $code = match ($status) {
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            429 => 'too_many_requests',
            default => 'http_error',
        };

        // Model-not-found messages name internal classes; keep them out of responses.
        $message = $status === 404 || $e->getMessage() === ''
            ? (Response::$statusTexts[$status] ?? 'Error').'.'
            : $e->getMessage();

        return [$status, $code, $message, null];
    }
}
