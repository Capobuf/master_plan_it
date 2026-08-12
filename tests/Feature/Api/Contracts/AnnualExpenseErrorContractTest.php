<?php

namespace Tests\Feature\Api\Contracts;

use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class AnnualExpenseErrorContractTest extends TestCase
{
    use InteractsWithApiFoundation;

    public function test_invalid_correlation_is_replaced_by_uuidv4_in_a_sanitized_not_found_envelope(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'not-a-v4')->getJson('/api/v1/expenses/999999?planning_year_id=999999');

        $response->assertStatus(401)->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');
        $correlation = $response->headers->get('X-Correlation-ID');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) $correlation);
        $this->assertNotSame('not-a-v4', $correlation);
    }

    public function test_target_error_catalogue_exposes_reconciliation_failure_without_payload_leakage(): void
    {
        $contents = file_get_contents(base_path('app/Support/Api/ApiErrorResponse.php'));
        $this->assertStringContainsString('ECONOMIC_RECONCILIATION_FAILED', $contents);
        $this->assertStringContainsString('RESOURCE_NOT_FOUND', $contents);
        $this->assertStringContainsString('VALIDATION_FAILED', $contents);
    }
}
