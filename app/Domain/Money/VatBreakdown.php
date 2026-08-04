<?php

namespace App\Domain\Money;

use DomainException;

final readonly class VatBreakdown
{
    public function __construct(
        private Money $net,
        private Money $vat,
        private Money $gross,
    ) {
        if (
            $net->currency() !== $vat->currency()
            || $net->currency() !== $gross->currency()
            || bccomp(bcadd($net->amount(), $vat->amount(), 2), $gross->amount(), 2) !== 0
        ) {
            throw new DomainException('AMOUNT_RECONCILIATION_FAILED');
        }
    }

    public function net(): Money
    {
        return $this->net;
    }

    public function vat(): Money
    {
        return $this->vat;
    }

    public function gross(): Money
    {
        return $this->gross;
    }
}
