<?php

namespace App\Support\Api;

use App\Domain\Expenses\Exceptions\PlafondInsufficientException;
use App\Domain\Plafonds\Services\PlafondReadAuthorizer;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
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
        'BUDGET_STATE_CONFLICT' => [409, 'The economic basis is permanently locked.'],
        'BUDGET_PROPOSAL_EMPTY' => [409, 'La proposta di Budget non contiene componenti economici.'],
        'BUDGET_COMPOSITION_STALE' => [409, 'La composizione del Budget è cambiata. Riesamina la proposta.'],
        'BUDGET_APPROVAL_ANNULMENT_BLOCKED' => [409, 'L\'annullamento è bloccato da eventi operativi.'],
        'ECONOMIC_RECONCILIATION_FAILED' => [500, 'The economic projection could not be reconciled.'],
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
        'ATTACHMENT_QUOTA_EXCEEDED' => [422, 'The attachment would exceed the Tenant storage quota.'],
        'ATTACHMENT_FILE_MISSING' => [500, 'The attachment metadata exists but its private file is unavailable.'],
        'ATTACHMENT_STORAGE_FAILURE' => [500, 'The private attachment storage operation failed.'],
    ];

    public static function from(Throwable $exception, Request $request): JsonResponse
    {
        [$status, $code, $message, $fields, $details] = self::details($exception, $request);
        $correlationId = CorrelationId::resolveFor($request)->value();

        $error = [
            'code' => $code,
            'message' => $message,
            'fields' => $fields,
            'correlation_id' => $correlationId,
        ];
        if ($details !== null) {
            $error['details'] = $details;
        }
        $response = response()->json(['error' => $error], $status);

        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }

    /** @return array{int, string, string, array<string, mixed>, ?array<string, mixed>} */
    private static function details(Throwable $exception, Request $request): array
    {
        if ($exception instanceof PlafondInsufficientException) {
            return [
                422,
                'PLAFOND_INSUFFICIENT',
                'La capienza del Plafond non è sufficiente.',
                [$exception->insufficiency->field => [
                    'Riduci l\'importo, aumenta l\'Allocazione, dividi la Spesa o rimuovi la copertura.',
                ]],
                $exception->insufficiency->details(self::canExposeExpenseDetails($request)),
            ];
        }

        if ($exception instanceof ValidationException) {
            return [422, 'VALIDATION_FAILED', 'The submitted data is invalid.', $exception->errors(), null];
        }

        if ($exception instanceof AuthenticationException || $exception instanceof UnauthorizedHttpException) {
            return [401, 'AUTHENTICATION_REQUIRED', 'Authentication is required.', [], null];
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return self::domain($exception->getMessage(), 403, 'PERMISSION_DENIED', 'You are not authorized to perform this operation.');
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return [404, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', [], null];
        }

        if ($exception instanceof DomainException) {
            if ($exception->getMessage() === 'BUDGET_APPROVAL_ANNULMENT_BLOCKED') {
                return [
                    409,
                    'BUDGET_APPROVAL_ANNULMENT_BLOCKED',
                    'L\'annullamento è bloccato da eventi operativi.',
                    [],
                    ['blockers' => [
                        'actuals' => [],
                        'extra_budget' => [],
                        'rectifications' => [],
                        'closures' => [],
                    ]],
                ];
            }

            return self::domain($exception->getMessage(), 409, 'DOMAIN_CONFLICT', 'The requested change conflicts with current data.');
        }

        if ($exception instanceof TokenMismatchException) {
            return [419, 'CSRF_TOKEN_MISMATCH', 'The security token is invalid or expired.', [], null];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return match ($status) {
                404 => [404, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', [], null],
                405 => [405, 'METHOD_NOT_ALLOWED', 'The requested method is not allowed.', [], null],
                419 => [419, 'CSRF_TOKEN_MISMATCH', 'The security token is invalid or expired.', [], null],
                422 => [422, 'VALIDATION_FAILED', 'The submitted data is invalid.', [], null],
                429 => [429, 'RATE_LIMITED', 'Too many requests. Try again later.', [], null],
                default => [$status >= 400 && $status < 600 ? $status : 500, self::statusCode($status), 'The request could not be completed.', [], null],
            };
        }

        return [500, 'INTERNAL_ERROR', 'An unexpected error occurred.', [], null];
    }

    private static function canExposeExpenseDetails(Request $request): bool
    {
        $actor = $request->user();
        $context = $request->attributes->get(TenantContext::class);

        return $actor instanceof User
            && $context instanceof TenantContext
            && app(PlafondReadAuthorizer::class)->canViewAny($actor, $context);
    }

    /** @return array{int, string, string, array<string, mixed>, null} */
    private static function domain(string $rawCode, int $fallbackStatus, string $fallbackCode, string $fallbackMessage): array
    {
        if (isset(self::DOMAIN_ERRORS[$rawCode])) {
            [$status, $message] = self::DOMAIN_ERRORS[$rawCode];

            return [$status, $rawCode, $message, [], null];
        }

        return [$fallbackStatus, $fallbackCode, $fallbackMessage, [], null];
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
