<?php

namespace App\Domain\Economics\Services;

use App\Domain\Economics\Data\EconomicMeasure;
use DomainException;

final class VatCalculator
{
    public function __construct(private readonly MoneyCalculator $money) {}

    public function fromExcluded(string $net, string $rate, string $basis): EconomicMeasure
    {
        $net = $this->money->normalize($net);
        $rate = $this->rate($rate);
        $vat = $this->money->rounded(bcdiv(bcmul($net, $rate, 12), '100', 12));

        return EconomicMeasure::fromAmounts($net, $vat, $this->money->add($net, $vat), $basis);
    }

    public function fromIncluded(string $gross, string $rate, string $basis): EconomicMeasure
    {
        $gross = $this->money->normalize($gross);
        $rate = $this->rate($rate);
        $net = $this->money->rounded(bcdiv(bcmul($gross, '100', 12), bcadd('100', $rate, 12), 12));

        return EconomicMeasure::fromAmounts($net, $this->money->subtract($gross, $net), $gross, $basis);
    }

    private function rate(string $rate): string
    {
        if (! preg_match('/^(0|[1-9]\d*)(\.\d{1,2})?$/D', $rate)) {
            throw new DomainException('INVALID_VAT_RATE');
        }

        return $this->money->normalize($rate, false);
    }
}
