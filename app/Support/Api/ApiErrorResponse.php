<?php

namespace App\Support\Api;

use App\Support\Diagnostics\CorrelationId;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * The API boundary has one deliberately small error envelope. Domain exception
 * messages are treated as codes and are never sent directly to the browser.
 */
final class ApiErrorResponse
{
    /** @var array<string, array{int, string}> */
    private const DOMAIN_ERRORS = [
        'STALE_VERSION' => [409, 'The resource changed. Refresh and try again.'],
        'REFERENCED_RECORD_DELETE_DENIED' => [409, 'The resource cannot be deleted while referenced.'],
        'PROJECT_HAS_LINKED_EXPENSES' => [409, 'The project cannot be deleted while current expenses are linked.'],
        'TENANT_ROLE_IN_USE' => [409, 'The role cannot be changed while it is assigned.'],
        'TENANT_RELATION_MISMATCH' => [409, 'The requested change conflicts with tenant data.'],
        'CURRENT_PASSWORD_INVALID' => [422, 'The current password is incorrect.'],
        'DESTRUCTIVE_CONFIRMATION_REQUIRED' => [422, 'The confirmation value is invalid.'],
        'TENANT_CONTEXT_REQUIRED' => [403, 'A valid tenant context is required.'],
        'TENANT_INACTIVE' => [403, 'The tenant is inactive.'],
        'ACCOUNT_INACTIVE' => [403, 'The account is inactive.'],
        'PERMISSION_DENIED' => [403, 'You are not authorized to perform this operation.'],
        'PLATFORM_ABILITY_PROTECTED' => [403, 'The requested ability is protected.'],
        'HISTORY_BEFORE_ACTIVATION' => [422, 'Historical data is not available before annual history activation.'],
        'INVALID_HISTORY_CUTOFF' => [422, 'The requested historical cutoff is invalid.'],
        'INVALID_REPORT_GROUPING' => [422, 'The requested report grouping is invalid.'],
        'APPROVED_DIMENSION_REALLOCATION_REQUIRED' => [422, 'Approved dimensions require an explicit reallocation decision.'],
        'EXPENSE_BULK_ITEM_NOT_APPLICABLE' => [422, 'At least one selected expense cannot use this bulk action.'],
        'TENANT_BUDGET_BASIS_LOCKED' => [409, 'The Tenant budget basis cannot change while approvals exist.'],
    ];

    public static function from(Throwable $exception, Request $request): JsonResponse
    {
        [$status, $code, $message, $fields] = self::details($exception);
        $correlationId = CorrelationId::resolveFor($request)->value();

        $response = response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => $fields,
                'correlation_id' => $correlationId,
            ],
        ], $status);

        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }

    /** @return array{int, string, string, array<string, mixed>} */
    private static function details(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [422, 'VALIDATION_FAILED', 'The submitted data is invalid.', $exception->errors()];
        }

        if ($exception instanceof AuthenticationException || $exception instanceof UnauthorizedHttpException) {
            return [401, 'AUTHENTICATION_REQUIRED', 'Authentication is required.', []];
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return self::domain($exception->getMessage(), 403, 'PERMISSION_DENIED', 'You are not authorized to perform this operation.');
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return [404, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', []];
        }

        if ($exception instanceof DomainException) {
            return self::domain($exception->getMessage(), 409, 'DOMAIN_CONFLICT', 'The requested change conflicts with current data.');
        }

        if ($exception instanceof TokenMismatchException) {
            return [419, 'CSRF_TOKEN_MISMATCH', 'The security token is invalid or expired.', []];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return match ($status) {
                404 => [404, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', []],
                405 => [405, 'METHOD_NOT_ALLOWED', 'The requested method is not allowed.', []],
                419 => [419, 'CSRF_TOKEN_MISMATCH', 'The security token is invalid or expired.', []],
                422 => [422, 'VALIDATION_FAILED', 'The submitted data is invalid.', []],
                429 => [429, 'RATE_LIMITED', 'Too many requests. Try again later.', []],
                default => [$status >= 400 && $status < 600 ? $status : 500, self::statusCode($status), 'The request could not be completed.', []],
            };
        }

        return [500, 'INTERNAL_ERROR', 'An unexpected error occurred.', []];
    }

    /** @return array{int, string, string, array<string, mixed>} */
    private static function domain(string $rawCode, int $fallbackStatus, string $fallbackCode, string $fallbackMessage): array
    {
        if (isset(self::DOMAIN_ERRORS[$rawCode])) {
            [$status, $message] = self::DOMAIN_ERRORS[$rawCode];

            return [$status, $rawCode, $message, []];
        }

        return [$fallbackStatus, $fallbackCode, $fallbackMessage, []];
    }

    private static function statusCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'AUTHENTICATION_REQUIRED',
            403 => 'PERMISSION_DENIED',
            404 => 'RESOURCE_NOT_FOUND',
            409 => 'DOMAIN_CONFLICT',
            419 => 'CSRF_TOKEN_MISMATCH',
            422 => 'VALIDATION_FAILED',
            429 => 'RATE_LIMITED',
            default => 'INTERNAL_ERROR',
        };
    }
}
