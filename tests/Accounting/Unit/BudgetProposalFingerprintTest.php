<?php

namespace Tests\Accounting\Unit;

use App\Domain\Budget\Data\ApprovalContributor;
use App\Domain\Budget\Enums\ApprovalContributorKind;
use App\Domain\Budget\Services\BudgetProposalFingerprint;
use App\Domain\Economics\Data\EconomicMeasure;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BudgetProposalFingerprintTest extends TestCase
{
    public function test_order_nulls_unicode_and_fixed_decimals_have_one_canonical_digest(): void
    {
        $composed = "Cafe\u{0301}";
        $normalized = "Caf\u{00E9}";
        $first = $this->contributor('expense-row:2', 2, $composed);
        $second = $this->contributor('expense-row:1', 1, null);
        $equivalentFirst = $this->contributor('expense-row:2', 2, $normalized);
        $fingerprint = app(BudgetProposalFingerprint::class);

        $left = $fingerprint->fingerprint(10, 20, 'EUR', 'net', [$first, $second]);
        $right = $fingerprint->fingerprint(10, 20, 'EUR', 'net', [$second, $equivalentFirst]);

        $this->assertSame($left, $right);
        $this->assertMatchesRegularExpression('/^sha256:[0-9a-f]{64}$/D', $left);
        $json = $fingerprint->canonicalJson(10, 20, 'EUR', 'net', [$first, $second]);
        $this->assertSame(
            ['basis', 'contributors', 'currency', 'planning_year_id', 'projection_version', 'schema_version', 'tenant_id'],
            array_keys(json_decode($json, true, 512, JSON_THROW_ON_ERROR)),
        );
        $this->assertStringContainsString('"contract_title":null', $json);
        $this->assertStringContainsString('"net":"10.00"', $json);
        $this->assertLessThan(strpos($json, 'expense-row:2'), strpos($json, 'expense-row:1'));
    }

    public function test_money_spellings_normalize_to_one_golden_digest(): void
    {
        $fingerprint = app(BudgetProposalFingerprint::class);
        $canonical = $this->contributorWithMeasure(new EconomicMeasure('10.00', '2.20', '12.20', '10.00'));
        $short = $this->contributorWithMeasure(new EconomicMeasure('10', '2.2', '12.2', '10.0'));

        $this->assertSame(
            'sha256:0f0523e52b5c91b589ed7c01ac7ff4f6a3581c5c79ac42b850e7a7a7247ed381',
            $fingerprint->fingerprint(10, 20, 'EUR', 'net', [$canonical]),
        );
        $this->assertSame(
            $fingerprint->fingerprint(10, 20, 'EUR', 'net', [$canonical]),
            $fingerprint->fingerprint(10, 20, 'EUR', 'net', [$short]),
        );
    }

    #[DataProvider('invalidMoneyProvider')]
    public function test_noncanonical_money_precision_and_negative_zero_are_rejected(string $amount): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('INVALID_CANONICAL_MONEY');

        app(BudgetProposalFingerprint::class)->fingerprint(
            10,
            20,
            'EUR',
            'net',
            [$this->contributorWithMeasure(new EconomicMeasure($amount, '0.00', $amount, $amount))],
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMoneyProvider(): iterable
    {
        yield 'precision greater than two' => ['10.000'];
        yield 'negative integer zero' => ['-0'];
        yield 'negative decimal zero' => ['-0.00'];
    }

    public function test_source_version_and_same_total_dimension_changes_change_the_digest(): void
    {
        $fingerprint = app(BudgetProposalFingerprint::class);
        $original = $this->contributor('expense-row:1', 1, 'Centro A');
        $versionChanged = $this->contributor('expense-row:1', 2, 'Centro A');
        $dimensionChanged = $this->contributor('expense-row:1', 1, 'Centro B');

        $digest = fn (ApprovalContributor $item): string => $fingerprint->fingerprint(10, 20, 'EUR', 'net', [$item]);

        $this->assertNotSame($digest($original), $digest($versionChanged));
        $this->assertNotSame($digest($original), $digest($dimensionChanged));
    }

    private function contributor(string $identity, int $version, ?string $contractTitle): ApprovalContributor
    {
        return new ApprovalContributor(
            sourceIdentity: $identity,
            kind: ApprovalContributorKind::OrdinaryCurrentPlanning,
            sourceLockVersion: $version,
            expenseId: 9,
            expenseTitle: 'Spesa',
            expenseKind: 'ordinary',
            rowId: (int) substr($identity, strrpos($identity, ':') + 1),
            rowType: 'quote',
            rowDescription: 'Preventivo',
            costCenterId: 3,
            costCenterName: 'Centro',
            vendorId: null,
            vendorName: null,
            projectId: null,
            projectTitle: null,
            contractId: $contractTitle === null ? null : 4,
            contractTitle: $contractTitle,
            amount: EconomicMeasure::fromAmounts('10.00', '2.20', '12.20', 'net'),
            drillDownAuthorized: true,
            drillDownHref: '/api/v1/expenses/9',
        );
    }

    private function contributorWithMeasure(EconomicMeasure $measure): ApprovalContributor
    {
        $contributor = $this->contributor('expense-row:1', 1, null);

        return new ApprovalContributor(
            sourceIdentity: $contributor->sourceIdentity,
            kind: $contributor->kind,
            sourceLockVersion: $contributor->sourceLockVersion,
            expenseId: $contributor->expenseId,
            expenseTitle: $contributor->expenseTitle,
            expenseKind: $contributor->expenseKind,
            rowId: $contributor->rowId,
            rowType: $contributor->rowType,
            rowDescription: $contributor->rowDescription,
            costCenterId: $contributor->costCenterId,
            costCenterName: $contributor->costCenterName,
            vendorId: $contributor->vendorId,
            vendorName: $contributor->vendorName,
            projectId: $contributor->projectId,
            projectTitle: $contributor->projectTitle,
            contractId: $contributor->contractId,
            contractTitle: $contributor->contractTitle,
            amount: $measure,
            drillDownAuthorized: true,
            drillDownHref: '/api/v1/expenses/9',
        );
    }
}
