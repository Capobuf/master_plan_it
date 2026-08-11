<?php

namespace Tests\Feature\Diagnostics;

use App\Http\Middleware\AssignCorrelationId;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Stringable;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('d', 32)),
            'logging.default' => 'null',
        ]);
    }

    public function test_missing_header_creates_one_lowercase_uuid_v4_across_request_container_response_and_log(): void
    {
        $events = [];
        $this->captureLogs($events);
        $this->registerInspectionRoute('/api/_contract/correlation/missing', 'missing-header');

        $response = $this->getJson('/api/_contract/correlation/missing')->assertOk();
        $id = (string) $response->headers->get(CorrelationId::HEADER);

        $this->assertTrue(Str::isUuid($id, 4));
        $this->assertSame(strtolower($id), $id);
        $response->assertJson([
            'request_id' => $id,
            'container_id' => $id,
            'same_instance' => true,
        ]);
        $this->assertLogHasCorrelation($events, 'missing-header', $id);
        $this->assertArrayNotHasKey(CorrelationId::LOG_CONTEXT_KEY, Log::sharedContext());
    }

    public function test_valid_uuid_v4_is_preserved_with_lowercase_normalization_and_invalid_values_are_replaced(): void
    {
        $valid = strtolower((string) Str::uuid());
        $uppercase = strtoupper((string) Str::uuid());
        $this->registerInspectionRoute('/api/_contract/correlation/inbound/{case}', 'inbound-header');

        $validResponse = $this->withHeader(CorrelationId::HEADER, $valid)
            ->getJson('/api/_contract/correlation/inbound/valid')
            ->assertOk();

        $this->assertSame($valid, $validResponse->headers->get(CorrelationId::HEADER));
        $validResponse->assertJsonPath('request_id', $valid);

        $uppercaseResponse = $this->withHeader(CorrelationId::HEADER, $uppercase)
            ->getJson('/api/_contract/correlation/inbound/uppercase')
            ->assertOk();

        $this->assertSame(strtolower($uppercase), $uppercaseResponse->headers->get(CorrelationId::HEADER));
        $uppercaseResponse->assertJsonPath('request_id', strtolower($uppercase));

        foreach (['not-a-uuid', '550e8400-e29b-11d4-a716-446655440000'] as $invalid) {
            $response = $this->withHeader(CorrelationId::HEADER, $invalid)
                ->getJson('/api/_contract/correlation/inbound/invalid')
                ->assertOk();
            $replacement = (string) $response->headers->get(CorrelationId::HEADER);

            $this->assertTrue(Str::isUuid($replacement, 4));
            $this->assertSame(strtolower($replacement), $replacement);
            $this->assertNotSame($invalid, $replacement);
            $response->assertJsonPath('request_id', $replacement);
        }
    }

    public function test_exception_hooks_assign_one_correlation_before_middleware_without_a_preassigned_attribute(): void
    {
        $events = [];
        $this->captureLogs($events);
        $inbound = strtoupper((string) Str::uuid());
        $expected = strtolower($inbound);
        $request = Request::create(
            '/api/_contract/correlation/pre-middleware',
            'GET',
            server: ['HTTP_X_CORRELATION_ID' => $inbound],
        );
        $this->assertFalse($request->attributes->has(CorrelationId::class));
        $this->app->instance('request', $request);
        $this->app->forgetInstance(CorrelationId::class);
        $exception = new RuntimeException('pre-middleware-correlation-failure');
        $handler = app(ExceptionHandler::class);

        $handler->report($exception);
        $response = $handler->render($request, $exception);
        $attributeId = $request->attributes->get(CorrelationId::class);

        $this->assertInstanceOf(CorrelationId::class, $attributeId);
        $this->assertSame($expected, (string) $attributeId);
        $this->assertSame($attributeId, app(CorrelationId::class));
        $this->assertSame($expected, $response->headers->get(CorrelationId::HEADER));
        $this->assertLogHasCorrelation($events, 'pre-middleware-correlation-failure', $expected);

        app(AssignCorrelationId::class)->terminate($request, $response);

        $this->assertFalse($request->attributes->has(CorrelationId::class));
        $this->assertArrayNotHasKey(CorrelationId::LOG_CONTEXT_KEY, Log::sharedContext());
    }

    public function test_authorization_and_unexpected_error_responses_keep_the_request_id_through_logging_and_rendering(): void
    {
        $events = [];
        $this->captureLogs($events);
        Route::get('/api/_contract/correlation/authorization', function (): never {
            Log::notice('authorization-observed');

            throw new AuthorizationException('PERMISSION_DENIED');
        });
        Route::get('/api/_contract/correlation/unexpected', function (): never {
            throw new RuntimeException('unexpected-correlation-failure');
        });

        $authorizationId = strtolower((string) Str::uuid());
        $authorization = $this->withHeader(CorrelationId::HEADER, $authorizationId)
            ->getJson('/api/_contract/correlation/authorization')
            ->assertForbidden()
            ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'correlation_id']])
            ->assertJsonPath('error.code', 'PERMISSION_DENIED')
            ->assertJsonPath('error.fields', [])
            ->assertJsonPath('error.correlation_id', $authorizationId);

        $this->assertSame($authorizationId, $authorization->headers->get(CorrelationId::HEADER));

        $this->assertTrue(Str::isUuid($authorizationId, 4));
        $this->assertLogHasCorrelation($events, 'authorization-observed', $authorizationId);
        $this->assertArrayNotHasKey(CorrelationId::LOG_CONTEXT_KEY, Log::sharedContext());

        $unexpectedId = strtolower((string) Str::uuid());
        $unexpected = $this->withHeader(CorrelationId::HEADER, $unexpectedId)
            ->getJson('/api/_contract/correlation/unexpected')
            ->assertStatus(500)
            ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'correlation_id']])
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertJsonPath('error.fields', [])
            ->assertJsonPath('error.correlation_id', $unexpectedId);

        $this->assertSame($unexpectedId, $unexpected->headers->get(CorrelationId::HEADER));

        $this->assertTrue(Str::isUuid($unexpectedId, 4));
        $this->assertNotSame($authorizationId, $unexpectedId);
        $this->assertLogHasCorrelation($events, 'unexpected-correlation-failure', $unexpectedId);
        $this->assertArrayNotHasKey(CorrelationId::LOG_CONTEXT_KEY, Log::sharedContext());
    }

    public function test_consecutive_requests_have_distinct_scoped_and_log_context_without_leakage(): void
    {
        $events = [];
        $this->captureLogs($events);
        $first = strtolower((string) Str::uuid());
        $second = strtolower((string) Str::uuid());
        $this->registerInspectionRoute('/api/_contract/correlation/isolation/{case}', 'request-observed');

        foreach ([['first', $first], ['second', $second]] as [$case, $expected]) {
            $response = $this->withHeader(CorrelationId::HEADER, $expected)
                ->getJson('/api/_contract/correlation/isolation/'.$case)
                ->assertOk();

            $this->assertSame($expected, $response->headers->get(CorrelationId::HEADER));
            $response->assertJson([
                'request_id' => $expected,
                'container_id' => $expected,
                'same_instance' => true,
            ]);
            $this->assertArrayNotHasKey(CorrelationId::LOG_CONTEXT_KEY, Log::sharedContext());
        }

        $matching = array_values(array_filter(
            $events,
            fn (MessageLogged $event): bool => $event->message === 'request-observed',
        ));

        $this->assertCount(2, $matching);
        $this->assertSame($first, $matching[0]->context[CorrelationId::LOG_CONTEXT_KEY] ?? null);
        $this->assertSame($second, $matching[1]->context[CorrelationId::LOG_CONTEXT_KEY] ?? null);
        $this->assertNotSame(
            $matching[0]->context[CorrelationId::LOG_CONTEXT_KEY] ?? null,
            $matching[1]->context[CorrelationId::LOG_CONTEXT_KEY] ?? null,
        );
    }

    public function test_terminal_cleanup_preserves_preexisting_non_correlation_log_context(): void
    {
        $events = [];
        $this->captureLogs($events);
        Log::shareContext(['deployment' => 'blue']);
        $this->registerInspectionRoute('/api/_contract/correlation/preserved-context', 'preserved-context');

        try {
            $response = $this->getJson('/api/_contract/correlation/preserved-context')->assertOk();
            $correlationId = (string) $response->headers->get(CorrelationId::HEADER);
            $event = collect($events)->first(
                fn (MessageLogged $message): bool => $message->message === 'preserved-context',
            );

            $this->assertInstanceOf(MessageLogged::class, $event);
            $this->assertSame('blue', $event->context['deployment'] ?? null);
            $this->assertSame($correlationId, $event->context[CorrelationId::LOG_CONTEXT_KEY] ?? null);
            $this->assertSame('blue', Log::sharedContext()['deployment'] ?? null);
            $this->assertArrayNotHasKey(CorrelationId::LOG_CONTEXT_KEY, Log::sharedContext());
        } finally {
            Log::withoutContext();
            Log::flushSharedContext();
        }
    }

    public function test_correlation_value_is_readonly_stringable_and_terminal_cleanup_is_registered(): void
    {
        $reflection = new \ReflectionClass(CorrelationId::class);
        $middlewareProperty = new \ReflectionProperty(Kernel::class, 'middleware');
        $globalMiddleware = $middlewareProperty->getValue(app(Kernel::class));

        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->implementsInterface(Stringable::class));
        $this->assertTrue((new \ReflectionClass(AssignCorrelationId::class))->hasMethod('terminate'));
        $this->assertIsArray($globalMiddleware);

        $deferredIndex = array_search(InvokeDeferredCallbacks::class, $globalMiddleware, true);
        $correlationIndex = array_search(AssignCorrelationId::class, $globalMiddleware, true);

        $this->assertIsInt($deferredIndex);
        $this->assertIsInt($correlationIndex);
        $this->assertGreaterThan($deferredIndex, $correlationIndex);
    }

    /** @param array<int, MessageLogged> $events */
    private function captureLogs(array &$events): void
    {
        Log::listen(function (MessageLogged $event) use (&$events): void {
            $events[] = $event;
        });
    }

    private function registerInspectionRoute(string $uri, string $message): void
    {
        Route::get($uri, function () use ($message) {
            $requestId = request()->attributes->get(CorrelationId::class);
            $containerId = app(CorrelationId::class);

            Log::info($message);

            return response()->json([
                'request_id' => (string) $requestId,
                'container_id' => (string) $containerId,
                'same_instance' => $requestId === $containerId,
            ]);
        });
    }

    /** @param array<int, MessageLogged> $events */
    private function assertLogHasCorrelation(array $events, string $message, string $correlationId): void
    {
        $event = collect($events)->first(
            fn (MessageLogged $event): bool => $event->message === $message,
        );

        $this->assertInstanceOf(MessageLogged::class, $event);
        $this->assertSame($correlationId, $event->context[CorrelationId::LOG_CONTEXT_KEY] ?? null);
    }
}
