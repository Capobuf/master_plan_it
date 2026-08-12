<?php

namespace App\Domain\Economics\Data;

use App\Domain\Economics\Services\MoneyCalculator;
use DomainException;

final readonly class EconomicMeasure
{
    public function __construct(public string $net, public string $vat, public string $gross, public string $official) {}

    public static function zero(string $basis): self
    {
        return new self('0.00', '0.00', '0.00', '0.00');
    }

    public static function fromAmounts(string $net, string $vat, string $gross, string $basis): self
    {
        if (! in_array($basis, ['net', 'gross'], true) || bccomp(bcadd($net, $vat, 2), $gross, 2) !== 0) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }

        return new self($net, $vat, $gross, $basis === 'gross' ? $gross : $net);
    }

    public function plus(self $other, string $basis): self
    {
        $money = new MoneyCalculator;

        return self::fromAmounts($money->add($this->net, $other->net), $money->add($this->vat, $other->vat), $money->add($this->gross, $other->gross), $basis);
    }
}
