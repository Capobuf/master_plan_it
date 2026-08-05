<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use RuntimeException;
use Tests\TestCase;

class TenantPresentationContextTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_locale_timezone_currency_and_vat_apply_inside_request_and_restore_afterward(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();
        $context = $this->context([
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'currency_code' => 'EUR',
            'default_vat_rate' => '22.125000',
        ]);
        $request = $this->requestWithContext($context);

        $response = app(ApplyTenantPresentationContext::class)->handle($request, function () use ($context) {
            $this->assertSame('it', App::currentLocale());
            $this->assertSame('Europe/Rome', date_default_timezone_get());
            $this->assertSame('EUR', $context->currencyCode);
            $this->assertSame('22.125000', $context->defaultVatRate);

            return response('presented');
        });

        $this->assertSame('presented', $response->getContent());
        $this->assertSame($originalLocale, App::currentLocale());
        $this->assertSame($originalTimezone, date_default_timezone_get());
    }

    public function test_presentation_state_restores_after_downstream_exception(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();
        $context = $this->context(['language_code' => 'fr', 'timezone' => 'Europe/Paris']);

        try {
            app(ApplyTenantPresentationContext::class)->handle(
                $this->requestWithContext($context),
                function (): never {
                    throw new RuntimeException('presentation failure');
                },
            );
            $this->fail('Downstream exception was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('presentation failure', $exception->getMessage());
            $this->assertSame($originalLocale, App::currentLocale());
            $this->assertSame($originalTimezone, date_default_timezone_get());
        }
    }

    public function test_invalid_tenant_timezone_fails_without_leaking_the_locale_or_timezone(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();
        $context = $this->context(['language_code' => 'fr', 'timezone' => 'Not/A_Timezone']);
        $downstreamCalled = false;

        try {
            app(ApplyTenantPresentationContext::class)->handle(
                $this->requestWithContext($context),
                function () use (&$downstreamCalled) {
                    $downstreamCalled = true;

                    return response('unsafe');
                },
            );
            $this->fail('Invalid tenant timezone was accepted.');
        } catch (\Throwable $exception) {
            $this->assertNotSame('', $exception->getMessage());
            $this->assertFalse($downstreamCalled);
            $this->assertSame($originalLocale, App::currentLocale());
            $this->assertSame($originalTimezone, date_default_timezone_get());
        }
    }

    public function test_http_component_and_console_style_iterations_do_not_leak_presentation_state(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();
        $contexts = [
            $this->context(['language_code' => 'it', 'timezone' => 'Europe/Rome', 'currency_code' => 'EUR']),
            $this->context(['language_code' => 'en', 'timezone' => 'UTC', 'currency_code' => 'USD']),
            $this->context(['language_code' => 'fr', 'timezone' => 'Europe/Paris', 'currency_code' => 'CHF']),
        ];

        foreach ($contexts as $index => $context) {
            $request = $this->requestWithContext($context);

            app(ApplyTenantPresentationContext::class)->handle($request, function () use ($context, $index) {
                $this->assertSame($context->languageCode, App::currentLocale());
                $this->assertSame($context->timezone, date_default_timezone_get());
                $this->assertSame($index === 0 ? 'EUR' : ($index === 1 ? 'USD' : 'CHF'), $context->currencyCode);

                return response('iteration');
            });

            $this->assertSame($originalLocale, App::currentLocale());
            $this->assertSame($originalTimezone, date_default_timezone_get());
        }
    }

    public function test_missing_presentation_context_fails_closed_without_mutating_process_state(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();

        try {
            app(ApplyTenantPresentationContext::class)->handle(Request::create('/missing-context'), fn () => response('unsafe'));
            $this->fail('Presentation middleware accepted a missing TenantContext.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
            $this->assertSame($originalLocale, App::currentLocale());
            $this->assertSame($originalTimezone, date_default_timezone_get());
        }
    }

    /** @param array<string, mixed> $attributes */
    private function context(array $attributes): TenantContext
    {
        $tenant = Tenant::factory()->create($attributes);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        return new TenantContext($tenant, $actor);
    }

    private function requestWithContext(TenantContext $context): Request
    {
        $request = Request::create('/presentation-context', 'GET');
        $request->attributes->set(TenantContext::class, $context);
        $request->setUserResolver(fn (): User => $context->actor);

        return $request;
    }
}
