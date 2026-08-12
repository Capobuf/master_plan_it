<?php

namespace Tests\Feature\Api\Contracts;

use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Expenses\Data\PlafondInsufficiency;
use App\Domain\Expenses\Exceptions\PlafondInsufficientException;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

final class PlafondErrorContractTest extends TestCase
{
    public function test_insufficiency_has_the_exact_structured_error_carrier(): void
    {
        $measure = static fn (string $value): EconomicMeasure => new EconomicMeasure($value, '0.00', $value, $value);
        $current = new PlafondEconomicProjection(
            41, 25, 'Plafond', 9, 'Infrastruttura', 'EUR', 'net',
            $measure('3000.00'), $measure('0.00'), $measure('500.00'), $measure('2500.00'), [], [],
        );
        $proposed = new PlafondEconomicProjection(
            41, 25, 'Plafond', 9, 'Infrastruttura', 'EUR', 'net',
            $measure('3000.00'), $measure('0.00'), $measure('3200.00'), $measure('-200.00'), [], [],
        );
        $impact = new PlafondImpact($current, $proposed, '2700.00', '200.00', false, []);
        $exception = new PlafondInsufficientException(new PlafondInsufficiency(
            41, 'EUR', 'net', '3000.00', '2500.00', '2700.00', '200.00',
            'rows.1.funded_plafond_expense_id', $impact,
        ));

        $response = ApiErrorResponse::from(
            $exception,
            Request::create('/api/v1/expenses', 'POST', server: ['HTTP_X_CORRELATION_ID' => 'd9428888-122b-4a8b-92e5-1c2e4f906234']),
        );
        $payload = $response->getData(true)['error'];

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('PLAFOND_INSUFFICIENT', $payload['code']);
        $this->assertSame(['rows.1.funded_plafond_expense_id' => [
            'Riduci l\'importo, aumenta l\'Allocazione, dividi la Spesa o rimuovi la copertura.',
        ]], $payload['fields']);
        $this->assertSame('EUR', $payload['details']['currency']);
        $this->assertSame('net', $payload['details']['basis']);
        $this->assertSame('3000.00', $payload['details']['allocated']);
        $this->assertSame('2500.00', $payload['details']['available']);
        $this->assertSame('2700.00', $payload['details']['required']);
        $this->assertSame('200.00', $payload['details']['shortage']);
        $this->assertSame('2700.00', $payload['details']['impact']['requested']);
        $this->assertSame('-200.00', $payload['details']['impact']['proposed']['available']['official']);
        $this->assertArrayNotHasKey('covered_lines', $payload['details']);
    }
}
