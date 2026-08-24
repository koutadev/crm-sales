<?php

namespace App\Support\Crm;

/**
 * 明細 1 行ぶんの金額内訳。
 *
 * 税込が正で、消費税額は税率グループの消費税額を按分した内訳。
 */
class LineAmount
{
    public function __construct(
        public readonly int $amountInclTax,
        public readonly int $taxAmount,
        public readonly int $amountExclTax,
    ) {}
}
