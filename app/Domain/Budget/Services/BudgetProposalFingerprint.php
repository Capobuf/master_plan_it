<?php

namespace App\Domain\Budget\Services;

use App\Domain\Budget\Data\ApprovalContributor;
use App\Domain\Budget\Data\BudgetCompositionEvidence;
use JsonException;
use Normalizer;

final class BudgetProposalFingerprint
{
    /** @param list<ApprovalContributor> $contributors */
    public function fingerprint(
        int $tenantId,
        int $planningYearId,
        string $currency,
        string $basis,
        array $contributors,
    ): string {
        return 'sha256:'.hash('sha256', $this->canonicalJson(
            $tenantId,
            $planningYearId,
            $currency,
            $basis,
            $contributors,
        ));
    }

    /**
     * @param  list<ApprovalContributor>  $contributors
     *
     * @throws JsonException
     */
    public function canonicalJson(
        int $tenantId,
        int $planningYearId,
        string $currency,
        string $basis,
        array $contributors,
    ): string {
        usort($contributors, static fn (ApprovalContributor $left, ApprovalContributor $right): int => strcmp(
            $left->sourceIdentity,
            $right->sourceIdentity,
        ));
        $payload = [
            'budget_basis' => $basis,
            'composition_schema_version' => BudgetCompositionEvidence::SCHEMA_VERSION,
            'contributors' => array_map(static fn (ApprovalContributor $item): array => $item->canonicalData(), $contributors),
            'currency_code' => $currency,
            'planning_year_id' => $planningYearId,
            'projection_version' => BudgetCompositionEvidence::PROJECTION_VERSION,
            'tenant_id' => $tenantId,
        ];

        return json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
