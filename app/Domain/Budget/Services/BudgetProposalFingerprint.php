<?php

namespace App\Domain\Budget\Services;

use App\Domain\Budget\Data\ApprovalContributor;
use App\Domain\Budget\Data\BudgetCompositionEvidence;
use DomainException;
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
            'basis' => $basis,
            'contributors' => array_map(fn (ApprovalContributor $item): array => $this->contributorData($item), $contributors),
            'currency' => $currency,
            'planning_year_id' => $planningYearId,
            'projection_version' => BudgetCompositionEvidence::PROJECTION_VERSION,
            'schema_version' => BudgetCompositionEvidence::SCHEMA_VERSION,
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

    /** @return array<string, mixed> */
    private function contributorData(ApprovalContributor $contributor): array
    {
        $data = $contributor->canonicalData();
        /** @var array<string, string> $amount */
        $amount = $data['amount'];
        foreach (['net', 'vat', 'gross', 'official'] as $component) {
            $amount[$component] = $this->money($amount[$component]);
        }
        $data['amount'] = $amount;

        return $data;
    }

    private function money(string $value): string
    {
        if (preg_match('/^-?\d+(?:\.(\d{1,2}))?$/D', $value) !== 1
            || (str_starts_with($value, '-') && bccomp($value, '0', 2) === 0)) {
            throw new DomainException('INVALID_CANONICAL_MONEY');
        }

        $canonical = bcadd($value, '0', 2);

        return $canonical === '-0.00' ? '0.00' : $canonical;
    }
}
