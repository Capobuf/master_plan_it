<?php

namespace Tests\Feature\Api\Budget;

use App\Support\Api\ApiErrorResponse;
use DomainException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BudgetApprovalErrorContractTest extends TestCase
{
    /** @param array<string, mixed>|null $details */
    #[DataProvider('stableErrors')]
    public function test_budget_approval_domain_errors_use_the_stable_envelope(
        string $code,
        string $message,
        ?array $details,
    ): void {
        $request = Request::create('/api/v1/budget/25/approve', 'POST');
        $request->headers->set('X-Correlation-ID', 'd9428888-122b-4a8b-92e5-1c2e4f906234');

        $response = ApiErrorResponse::from(new DomainException($code), $request);
        $payload = $response->getData(true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame($code, $payload['error']['code']);
        $this->assertSame($message, $payload['error']['message']);
        $this->assertSame([], $payload['error']['fields']);
        $this->assertSame('d9428888-122b-4a8b-92e5-1c2e4f906234', $payload['error']['correlation_id']);
        $this->assertSame($details, $payload['error']['details'] ?? null);
    }

    /** @return iterable<string, array{string, string, ?array<string, mixed>}> */
    public static function stableErrors(): iterable
    {
        yield 'empty proposal' => [
            'BUDGET_PROPOSAL_EMPTY',
            'La proposta di Budget non contiene componenti economici.',
            null,
        ];
        yield 'stale composition' => [
            'BUDGET_COMPOSITION_STALE',
            'La composizione del Budget è cambiata. Riesamina la proposta.',
            null,
        ];
        yield 'blocked annulment' => [
            'BUDGET_APPROVAL_ANNULMENT_BLOCKED',
            'L\'annullamento è bloccato da eventi operativi.',
            ['blockers' => [
                'actuals' => [],
                'extra_budget' => [],
                'rectifications' => [],
                'closures' => [],
            ]],
        ];
    }
}
