<?php

namespace App\Support\Crm;

/**
 * 税率ごとの金額内訳(税込 / 消費税 / 税抜)。
 *
 * 消費税は税率グループの税込合計に対して 1 回だけ切り捨てて求める。
 */
class TaxRateAmount
{
    public function __construct(
        public readonly int $ratePercent,
        public readonly int $amountInclTax,
        public readonly int $taxAmount,
    ) {}

    public function amountExclTax(): int
    {
        return $this->amountInclTax - $this->taxAmount;
    }

    public function label(): string
    {
        return $this->ratePercent.'%';
    }
}
