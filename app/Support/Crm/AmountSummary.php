<?php

namespace App\Support\Crm;

/**
 * 商談 1 件ぶんの金額サマリ。
 *
 * 税込合計が正。消費税・税抜は税率ごとに 1 回だけ切り捨てて求めた値の合算で、
 * 合計から逆算し直さない(明細と合計がずれないようにするため)。
 */
class AmountSummary
{
    /**
     * @param  list<TaxRateAmount>  $rateAmounts  税率ごとの内訳(税率の高い順)
     * @param  list<LineAmount>  $lineAmounts  明細ごとの内訳(入力と同じ並び)
     */
    public function __construct(
        public readonly array $rateAmounts,
        public readonly array $lineAmounts,
    ) {}

    public function totalInclTax(): int
    {
        return array_sum(array_map(
            static fn (TaxRateAmount $amount): int => $amount->amountInclTax,
            $this->rateAmounts,
        ));
    }

    public function totalTax(): int
    {
        return array_sum(array_map(
            static fn (TaxRateAmount $amount): int => $amount->taxAmount,
            $this->rateAmounts,
        ));
    }

    public function totalExclTax(): int
    {
        return $this->totalInclTax() - $this->totalTax();
    }
}
