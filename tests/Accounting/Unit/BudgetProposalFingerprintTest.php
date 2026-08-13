<?php

namespace Tests\Accounting\Unit;

use App\Domain\Budget\Data\ApprovalContributor;
use App\Domain\Budget\Enums\ApprovalContributorKind;
use App\Domain\Budget\Services\BudgetProposalFingerprint;
use App\Domain\Economics\Data\EconomicMeasure;
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
        $this->assertStringContainsString('"contract_title":null', $json);
        $this->assertStringContainsString('"net":"10.00"', $json);
        $this->assertLessThan(strpos($json, 'expense-row:2'), strpos($json, 'expense-row:1'));
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
}
